<?php

declare(strict_types=1);

namespace App\Classes\eHealth\Api;

use App\Classes\eHealth\EHealthRequest as Request;
use App\Classes\eHealth\EHealthResponse;
use GuzzleHttp\Promise\PromiseInterface;

class Approval extends Request
{
    public const string URL = '/api/approvals';

    /**
     * Create an approval request for a resource (e.g. Care Plan)
     *
     * @param array $payload
     * @return PromiseInterface|EHealthResponse
     */
    public function requestAccess(array $payload): PromiseInterface|EHealthResponse
    {
        return $this->post(self::URL, $payload);
    }

    /**
     * Get approvals list
     *
     * @param array $query
     * @return PromiseInterface|EHealthResponse
     */
    public function getApprovals(array $query = []): PromiseInterface|EHealthResponse
    {
        return $this->get(self::URL, $query);
    }

    /**
     * Cancel an approval
     *
     * @param string $approvalId
     * @param array $payload
     * @return PromiseInterface|EHealthResponse
     */
    public function cancel(string $approvalId, array $payload = []): PromiseInterface|EHealthResponse
    {
        return $this->patch(self::URL . "/{$approvalId}/actions/cancel", $payload);
    }
}
