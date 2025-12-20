<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Files_Retention\AppInfo;

use OCA\Files_Retention\EventListener;
use OCA\Files_Retention\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Server;
use OCP\SystemTag\ManagerEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'files_retention';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerService(IUserMountCache::class, function () {
			return Server::get(IMountProviderCollection::class)->getMountCache();
		});

		$context->registerEventListener(ManagerEvent::EVENT_DELETE, EventListener::class);

		$context->registerNotifierService(Notifier::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
	}
}
