<?php

namespace Topconsulenten\Promoter;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\RequestOptions;

/**
 * Client allows you to communicate with the Promoter API.
 *
 * @package Topconsulenten\Promoter
 */
class Client
{

    const ENDPOINT = 'https://www.topconsulenten.nl';

    const SORT_STATUS = 'status';
    const SORT_RATING = 'rating';
    const SORT_RATE = 'rate';

    const FILTER_CHAT = 'chat';
    const FILTER_PREMIUM = 'premium';

    // Status constants
	const STATUS_AVAILABLE = 1;
	const STATUS_UNAVAILABLE = 0;
	const STATUS_BUSY = 2;
	const STATUS_PAUSE = 3;
	const STATUS_FAKE_BUSY = 4;
	const STATUS_IN_CHAT = 5;

    protected $client;

    /**
     * Constructs the client to communicate with the Promoter API
     *
     * @param string $token Your authentication token
     * @param string $endpoint The endpoint to access (default should be used for production)
     */
    public function __construct($token, $endpoint = self::ENDPOINT)
    {
        $this->client = new \GuzzleHttp\Client([
            'base_url' => $endpoint,
            'defaults' => [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ]
            ]
//            RequestOptions::HEADERS => [
//                'Authorization' => 'Bearer ' . $token,
//            ]
        ]);
    }

    /**
     * This method creates an invite for a consultant to join Topconsulenten, with the given parameters (name and rate),
     * it returns a URL which should be triggered by a human to complete the registration.
     *
     * @param string $profileName The profile name for this consultant
     * @param int $rate The rate in eurocents
     * @param string $note A note
     * @return string The URL for registration completion
     * @throws InvalidCredentialsException
     * @throws UnexpectedResponseException
     */
    public function createConsultantInvite($profileName, $rate, $note)
    {
        try {
            $response = $this->client->post('/api/promoter/consultants/create', [
                'body' => json_encode([
                    'profile_name' => $profileName,
                    'rate' => $rate,
                    'note' => $note,
                ]),
            ]);
        } catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();

                if ($response->getStatusCode() == 401) {
                    throw new InvalidCredentialsException('Invalid credentials supplied');
                }

                if ($response->getStatusCode() != 200) {
                    throw new UnexpectedResponseException('The API returned a non-200 status code');
                }
            }

            throw new UnexpectedResponseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnexpectedResponseException($e->getMessage());
        }

        $result = json_decode($response->getBody(), true);

        if ($result['success']) {
            return $result['registration_url'];
        }

        return '';
    }

    /**
     * Retrieves the available consultants from the Topconsulenten API, based on the sorting and filtering arguments
     * passed to the function.
     *
     * @param string $sort Sort consultants based on this parameter
     * @param []string $filters Apply filters to the results, optional.
     * @return []mixed An associative array containing the consultants, if any.
     *
     * @throws InvalidCredentialsException
     * @throws InvalidFilterOptionException
     * @throws InvalidSortOptionException
     * @throws UnexpectedResponseException
     */
    public function getConsultants($sort = self::SORT_STATUS, $filters = [])
    {
        if (!$this->isValidSort($sort)) {
            throw new InvalidSortOptionException('Invalid option for sort supplied');
        }

        if ($this->hasInvalidFilters($filters)) {
            throw new InvalidFilterOptionException('One or more invalid filters passed');
        }

        $query = ['sort' => $sort];

        foreach ($filters as $name => $value) {
            $query[$name] = $value;
        }

        try {
            $response = $this->client->get('/api/promoter/consultants', [
                'query' => $query,
            ]);
        } catch (ClientException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();

                if ($response->getStatusCode() == 401) {
                    throw new InvalidCredentialsException('Invalid credentials supplied');
                }

                if ($response->getStatusCode() != 200) {
                    throw new UnexpectedResponseException('The API returned a non-200 status code');
                }
            }

            throw new UnexpectedResponseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnexpectedResponseException($e->getMessage());
        }

        $body = $response->getBody();
        $parsed = json_decode($body, true);

        return $parsed['promoted_consultants'];
    }

	/**
	 * Changes the availability of the consultant. Returns true on success, false otherwise
	 *
	 * @param string $consultantApiKey
	 * @param int $newStatus
	 *
	 * @return bool
	 */
    public function changeConsultantStatus($consultantApiKey, $newStatus) {

    	if (!is_numeric($newStatus)) {
    		throw new InvalidStatusException('The given new-status is invalid');
	    }

	    if (!$this->isValidStatus($newStatus)) {
    		throw new InvalidStatusException('The given new-status is invalid');
	    }

    	try {
		    $response = $this->client->get(sprintf('/api/consultant/status/change/%d', $newStatus), [
	            'headers' => [
	                'Authorization' => 'Bearer ' . $consultantApiKey
		        ]
		    ]);

		    if ($response->getStatusCode() > 200 && $response->getStatusCode() < 300) {
		    	return true;
		    }

	    } catch (ClientException $e) {
			if ($e->hasResponse()) {
				$response = $e->getResponse();
				if ($response->getStatusCode() == 401) {
					throw new InvalidCredentialsException('Invalid credentials supplied');
				}

				if ($response->getStatusCode() == 200 && $response->getStatusCode() != 202) {
					throw new UnexpectedResponseException('The API returned a non-20x status code');
				}
			}

			throw new UnexpectedResponseException($e->getMessage());
	    }

	    return false;
    }

    /**
     * Checks if the given sort is valid
     *
     * @param string $sort
     * @return bool
     */
    private function isValidSort($sort)
    {
        return in_array($sort, [self::SORT_RATE, self::SORT_RATING, self::SORT_STATUS]);
    }

    /**
     * Checks if the given filter is valid
     *
     * @param []string $filters
     * @return bool
     * @internal param string $filter
     */
    private function hasInvalidFilters($filters)
    {
        $allowedFilters = [self::FILTER_PREMIUM, self::FILTER_CHAT];
        $allowedValues = ['on', 'off'];

        foreach ($filters as $filter => $value) {
            if (!in_array($filter, $allowedFilters) || !in_array($value, $allowedValues)) {
                return true;
            }
        }
        return false;
    }

	/**
	 * Checks if the given status is valid
	 *
	 * @param $status
	 *
	 * @return bool
	 */
    private function isValidStatus($status) {
	    if ($status != self::STATUS_AVAILABLE &&
	        $status != self::STATUS_UNAVAILABLE &&
	        $status != self::STATUS_PAUSE &&
	        $status != self::STATUS_FAKE_BUSY
	    ) {
		    return false;
	    }

	    return true;
    }

}