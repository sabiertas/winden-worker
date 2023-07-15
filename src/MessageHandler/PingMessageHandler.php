<?php

namespace App\MessageHandler;

use App\Entity\Autocomplete;
use App\Entity\Compile;
use App\Message\PingMessage;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler]
final class PingMessageHandler implements MessageHandlerInterface
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private HttpClientInterface $client,
    ) {
    }

    public function __invoke(PingMessage $message)
    {
        $repository = $this->doctrine->getRepository($message->getEntity());

        /** @var Autocomplete|Compile $entity */
        $entity = $repository->findOneBy(['uuid' => $message->getUuid()]);

        switch ($message->getEntity()) {
            case Autocomplete::class:
                $action = 'autocomplete';
                break;
            case Compile::class:
                $action = 'compile';
                break;
            default:
                $action = 'unknown';
                break;
        }

        try {
            $response = $this->client->request('POST', $entity->getSite(), [
                'json' => [
                    'uuid' => $message->getUuid()->__toString(),
                    'action' => $action,
                    'status' => $entity->getStatus()->value,
                ],
                'headers' => [
                    'Worker-Nonce' => $entity->getNonce(),
                ],
            ]);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
