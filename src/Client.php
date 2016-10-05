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

    const ENDPOINT = 'https://topconsulenten.nl';

    const SORT_STATUS = 'status';
    const SORT_RATING = 'rating';
    const SORT_RATE = 'rate';

    const FILTER_CHAT = 'chat';
    const FILTER_PREMIUM = 'premium';

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
            'base_uri' => $endpoint,
            RequestOptions::HEADERS => [
                'Authorization' => 'Bearer ' . $token,
            ]
        ]);
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
            $response = $this->client->request('GET', '/api/promoter/consultants', [
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

}