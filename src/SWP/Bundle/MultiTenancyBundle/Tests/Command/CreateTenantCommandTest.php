<?php

/*
 * This file is part of the Superdesk Web Publisher MultiTenancy Bundle.
 *
 * Copyright 2016 Sourcefabric z.ú. and contributors.
 *
 * For the full copyright and license information, please see the
 * AUTHORS and LICENSE files distributed with this source code.
 *
 * @copyright 2016 Sourcefabric z.ú
 * @license http://www.superdesk.org/license
 */

namespace SWP\Bundle\MultiTenancyBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use SWP\Bundle\MultiTenancyBundle\Command\CreateTenantCommand;
use SWP\Component\MultiTenancy\Factory\TenantFactoryInterface;
use SWP\Component\MultiTenancy\Model\Organization;
use SWP\Component\MultiTenancy\Model\OrganizationInterface;
use SWP\Component\MultiTenancy\Model\Tenant;
use SWP\Component\MultiTenancy\Repository\OrganizationRepositoryInterface;
use SWP\Component\MultiTenancy\Repository\TenantRepositoryInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CreateTenantCommandTest extends TestCase
{
    const ORGANIZATION_CODE = '123456';

    private $commandTester;

    private $command;

    private $question;

    public function setUp()
    {
        $application = new Application();
        $application->add(new CreateTenantCommand());
        $this->command = $application->get('swp:tenant:create');
        $this->question = $this->command->getHelper('question');
    }

    /**
     * @covers \SWP\Bundle\MultiTenancyBundle\Command\CreateTenantCommand
     */
    public function testExecuteWhenCreatingNewTenant()
    {
        $tenant = new Tenant();
        $tenant->setCode('123abc');
        $this->command->setContainer($this->getMockContainer(null, new Organization(), $tenant, 'subdomain', 'domain.dev'));
        $this->commandTester = new CommandTester($this->command);
        $this->commandTester->setInputs(['domain.dev', 'Test', '123456']);
        $this->commandTester->execute(['command' => $this->command->getName()]);

        $this->assertContains(
            'Please enter domain:Please enter name:Please enter organization code:Tenant Test (code: 123abc) has been created and enabled!',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * @covers \SWP\Bundle\MultiTenancyBundle\Command\CreateTenantCommand
     */
    public function testExecuteWhenCreatingDefaultTenant()
    {
        $tenant = new Tenant();
        $tenant->setCode('123abc');
        $this->command->setContainer($this->getMockContainer(null, new Organization(), $tenant));
        $this->commandTester = new CommandTester($this->command);

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--default' => true,
        ]);

        $this->assertContains(
            'Tenant Default tenant (code: 123abc) has been created and enabled!',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * @covers \SWP\Bundle\MultiTenancyBundle\Command\CreateTenantCommand
     * @expectedException \InvalidArgumentException
     */
    public function testExecuteWhenCreatingDefaultTenantAndDefaultOrganizationDoesntExist()
    {
        $this->command->setContainer($this->getMockContainer());
        $this->commandTester = new CommandTester($this->command);

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--default' => true,
        ]);

        $this->assertRegExp(
            '/Default organization doesn\'t exist!/',
            $this->commandTester->getDisplay()
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @covers \SWP\Bundle\MultiTenancyBundle\Command\CreateTenantCommand
     */
    public function testExecuteWhenDefaultTenantExists()
    {
        $this->command->setContainer($this->getMockContainer(new Tenant()));
        $this->commandTester = new CommandTester($this->command);

        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--default' => true,
        ]);
    }

    /**
     * @covers \SWP\Bundle\MultiTenancyBundle\Command\CreateTenantCommand
     */
    public function testExecuteDisabledTenant()
    {
        $tenant = new Tenant();
        $tenant->setCode('123abc');
        $this->command->setContainer($this->getMockContainer(null, new Organization(), $tenant, 'example', 'example.com'));
        $this->commandTester = new CommandTester($this->command);
        $this->commandTester->setInputs(['example.com', 'Example', '123456']);
        $this->commandTester->execute([
            'command' => $this->command->getName(),
            '--disabled' => true,
        ]);

        $this->assertContains(
            'Please enter domain:Please enter name:Please enter organization code:Tenant Example (code: 123abc) has been created and disabled!',
            $this->commandTester->getDisplay()
        );
    }

    private function getMockContainer($mockTenant = null, $mockOrganization = null, $mockedTenantInFactory = null, $subdomain = 'default', $domain = 'localhost')
    {
        $mockRepoOrganization = $this->getMockBuilder(OrganizationRepositoryInterface::class)
            ->getMock();

        $mockRepoOrganization->expects($this->any())
            ->method('findOneByCode')
            ->with(self::ORGANIZATION_CODE)
            ->willReturn($mockOrganization);

        $mockRepoOrganization->expects($this->any())
            ->method('findOneByName')
            ->with(OrganizationInterface::DEFAULT_NAME)
            ->willReturn($mockOrganization);

        $mockRepo = $this->getMockBuilder(TenantRepositoryInterface::class)
            ->getMock();

        $mockRepo->expects($this->any())
            ->method('findOneBySubdomainAndDomain')
            ->with($subdomain, $domain)
            ->willReturn($mockTenant);

        $mockRepo->expects($this->any())
            ->method('findOneByDomain')
            ->with($domain)
            ->willReturn($mockTenant);

        $mockDoctrine = $this
            ->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();

        $mockDoctrine->expects($this->any())
            ->method('persist')
            ->will($this->returnValue(null));
        $mockDoctrine->expects($this->any())
            ->method('flush')
            ->will($this->returnValue(null));

        $mockContainer = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();

        $mockFactory = $this->getMockBuilder(TenantFactoryInterface::class)
            ->getMock();

        $mockFactory->expects($this->any())
            ->method('create')
            ->willReturn($mockedTenantInFactory);

        $mockContainer->expects($this->any())
            ->method('get')
            ->will(self::returnValueMap([
                ['swp.object_manager.tenant', 1, $mockDoctrine],
                ['swp.repository.tenant', 1, $mockRepo],
                ['swp.repository.organization', 1, $mockRepoOrganization],
                ['swp.factory.tenant', 1, $mockFactory],
            ]));

        $mockContainer->expects($this->any())
            ->method('getParameter')
            ->willReturn('localhost');

        return $mockContainer;
    }

    /**
     * @param $input
     *
     * @return resource
     */
    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fwrite($stream, $input);
        rewind($stream);

        return $stream;
    }
}
