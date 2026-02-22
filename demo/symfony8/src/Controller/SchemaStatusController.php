<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

/**
 * Shows whether entity mappings are in sync with the database.
 * Before running migrations: not in sync. After: in sync.
 */
class SchemaStatusController extends AbstractController
{
    #[Route('/', name: 'app_schema_status', methods: ['GET'])]
    public function index(EntityManagerInterface $em, ?Profiler $profiler = null): Response
    {
        if ($profiler !== null) {
            $profiler->disable();
        }

        $mappingErrors = [];
        $schemaErrors  = [];

        try {
            $validator     = new SchemaValidator($em);
            $mappingErrors = $validator->validateMapping();

            $metadata     = $em->getMetadataFactory()->getAllMetadata();
            $schemaTool   = new SchemaTool($em);
            $schemaSql    = $schemaTool->getUpdateSchemaSql($metadata);
            $schemaErrors = array_map('strval', $schemaSql);
        } catch (Throwable $e) {
            $schemaErrors = [$e->getMessage()];
        }

        $inSync = $mappingErrors === [] && $schemaErrors === [];

        return $this->render('schema_status/index.html.twig', [
            'in_sync'        => $inSync,
            'mapping_errors' => $mappingErrors,
            'schema_errors'  => $schemaErrors,
        ]);
    }
}
