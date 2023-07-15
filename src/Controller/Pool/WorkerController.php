<?php

namespace App\Controller\Pool;

use App\Entity\Autocomplete;
use App\Entity\Compile;
use App\Entity\Enum\RunStatus;
use App\Entity\Enum\Status;
use App\Entity\Run;
use App\Message\PingMessage;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/pool/worker', name: 'app_pool_worker_')]
class WorkerController extends AbstractController implements TokenAuthenticatedController
{
    public function __construct(
        private ManagerRegistry $doctrine,
        private MessageBusInterface $bus,
    ) {
    }

    #[Route('/pull', name: 'pull', methods: ['POST'])]
    public function pull(Request $request): Response
    {
        $payload = $request->toArray();

        $repository = $this->doctrine->getRepository($payload['entity']);

        /** @var Autocomplete|Compile $entity */
        $entity = $repository->findOneBy(['uuid' => $payload['uuid']]);

        return $this->json([
            'data' => $entity,
        ], Response::HTTP_OK, [], [
            'groups' => ['compile:read', 'autocomplete:read'],
        ]);
    }

    #[Route('/push', name: 'push', methods: ['POST'])]
    public function push(CacheInterface $cache, Request $request): Response
    {
        $payload = $request->toArray();

        $repository = $this->doctrine->getRepository($payload['entity']);

        /** @var Autocomplete|Compile $entity */
        $entity = $repository->findOneBy(['uuid' => $payload['uuid']]);

        $run = new Run();
        $entity->setRun($run);

        $run->setId($payload['run_id']);

        if ($payload['run_status'] && $payload['run_status'] === RunStatus::Success->value) {
            $run->setStatus(RunStatus::Success);
            $entity->setStatus(Status::Done);

            // Invalidate cache
            $cache->delete('workflow_' . $entity->getUuid());

            $cache->get('workflow_' . $entity->getUuid(), function () use ($payload) {
                return base64_decode($payload['payload']);
            });
        } else {
            $run->setStatus(RunStatus::Failure);
            $entity->setStatus(Status::Failed);
        }

        $this->doctrine->getManager()->persist($run);
        $this->doctrine->getManager()->flush();

        // dispatch ping signal
        $this->bus->dispatch(new PingMessage(
            $entity->getUuid(),
            $payload['entity'],
            $payload['run_id']
        ), [
            // wait 5 seconds before processing
            new DelayStamp(5000),
        ]);

        return $this->json([], Response::HTTP_OK);
    }
}
