<?php

declare(strict_types=1);
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
use OCP\IServerContainer;
use OCP\SystemTag\ManagerEvent;
use Psr\Container\ContainerInterface;

class Application extends App implements IBootstrap {

	public const APP_ID = 'files_retention';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerService(IUserMountCache::class, function (ContainerInterface $c) {
			/** @var IServerContainer $server */
			$server = $c->get(IServerContainer::class);
			return $server->get(IMountProviderCollection::class)->getMountCache();
		});

		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
		$server = $context->getServerContainer();
		$dispatcher = $server->getEventDispatcher();
		$dispatcher->addListener(ManagerEvent::EVENT_DELETE, function(ManagerEvent $event) use ($server) {
			/** @var EventListener $eventListener */
			$eventListener = $server->get(EventListener::class);

			$eventListener->tagDeleted($event->getTag());
		});
	}
}
