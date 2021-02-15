<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sylius\Bundle\ApiBundle\test\tests;

use Fidry\AliceDataFixtures\LoaderInterface;
use Fidry\AliceDataFixtures\Persistence\PurgeMode;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

trait Test
{
    /** @var string */
    private $JWTAdminUserToken;

    /** @var array */
    private $fixturesFiles;

    public function setFixturesFiles(array $fixturesFiles)
    {
        $this->fixturesFiles = array_merge(
            $fixturesFiles,
            ['test/fixtures/administrator.yaml', 'test/fixtures/channel.yaml']
        );
    }

    public function setUpTest(): void
    {
        parent::setUp();
        $kernel = self::bootKernel();
        $container = $kernel->getContainer();

        /** @var LoaderInterface $loader */
        $loader = $container->get('fidry_alice_data_fixtures.loader.doctrine');

        /** @var JWTTokenManagerInterface $JWTManager */
        $JWTManager = $container->get('lexik_jwt_authentication.jwt_manager');

        $objects = $loader->load($this->fixturesFiles, [], [], PurgeMode::createDeleteMode());

        $adminUser = $objects['admin'];

        $this->JWTAdminUserToken = $JWTManager->create($adminUser);
    }
}
