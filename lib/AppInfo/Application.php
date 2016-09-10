<?php
/**
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
 */

namespace OCA\Files_Retention\AppInfo;

use OCA\Files_Retention\EventListener;
use OCP\AppFramework\App;
use OCP\Files\Config\IUserMountCache;
use OCP\SystemTag\ManagerEvent;

class Application extends App {
	public function __construct(array $urlParams = array()) {
		parent::__construct('files_retention', $urlParams);

		$container = $this->getContainer();
		$server = $container->getServer();

		$container->registerService(IUserMountCache::class, function ($c) use ($server) {
			return $server->getMountProviderCollection()->getMountCache();
		});
	}

	public function registerEventListener() {
		$container = $this->getContainer();
		$dispatcher = $container->getServer()->getEventDispatcher();

		$dispatcher->addListener(ManagerEvent::EVENT_DELETE, function(ManagerEvent $event) use ($container) {
			/** @var EventListener $eventListener */
			$eventListener = $container->query(EventListener::class);

			$eventListener->tagDeleted($event->getTag());
		});
	}
}
