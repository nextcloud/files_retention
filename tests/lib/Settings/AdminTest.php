<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Retention\Tests\Settings;

use OCA\Files_Retention\Settings\Admin;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AdminTest extends TestCase {
	private IInitialState&MockObject $initialStateService;
	private IURLGenerator&MockObject $url;
	private IConfig&MockObject $config;
	private Admin $admin;

	protected function setUp(): void {
		parent::setUp();

		$this->initialStateService = $this->createMock(IInitialState::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->config = $this->createMock(IConfig::class);
		$this->admin = new Admin($this->initialStateService, $this->url, $this->config);
	}

	public function testGetForm(): void {
		$expected = new TemplateResponse('files_retention', 'admin', [], '');
		$this->assertEquals($expected, $this->admin->getForm());
	}

	public function testGetSection(): void {
		$this->assertSame('workflow', $this->admin->getSection());
	}

	public function testGetPriority(): void {
		$this->assertSame(80, $this->admin->getPriority());
	}
}
