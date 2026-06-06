<?php

namespace App\Tests\Mock;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use PHPUnit\Framework\MockObject\Generator;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DocumentManagerFactory
{
    public static function create(ContainerInterface $container): DocumentManager
    {
        $generator = new Generator();

        // Створюємо базовий мок для DocumentManager
        $dmMock = $generator->getMock(DocumentManager::class, [], [], '', false);

        // Створюємо мок для репозиторію
        $repoMock = $generator->getMock(DocumentRepository::class, [], [], '', false);
        $repoMock->method('findBy')->willReturn([]);

        $dmMock->method('getRepository')->willReturn($repoMock);

        return $dmMock;
    }
}
