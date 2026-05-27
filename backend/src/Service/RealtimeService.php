<?php

namespace App\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class RealtimeService
{
    public function __construct(
        private HubInterface $hub
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLISH EVENT
    |--------------------------------------------------------------------------
    */

    public function publish(
        string $topic,
        array $data
    ): void {

        $update = new Update(
            $topic,
            json_encode($data)
        );

        $this->hub->publish($update);
    }

    /*
    |--------------------------------------------------------------------------
    | NOTIFICATION EVENT
    |--------------------------------------------------------------------------
    */

    public function notification(
        int $userId,
        array $data
    ): void {

        $this->publish(
            sprintf(
                'notifications/user/%d',
                $userId
            ),
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TICKET EVENT
    |--------------------------------------------------------------------------
    */

    public function ticket(
        int $ticketId,
        array $data
    ): void {

        $this->publish(
            sprintf(
                'tickets/%d',
                $ticketId
            ),
            $data
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PROJECT EVENT
    |--------------------------------------------------------------------------
    */

    public function project(
        int $projectId,
        array $data
    ): void {

        $this->publish(
            sprintf(
                'projects/%d',
                $projectId
            ),
            $data
        );
    }
}