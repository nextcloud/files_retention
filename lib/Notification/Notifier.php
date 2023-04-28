<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files_Retention\Notification;

use OCA\Files_Retention\AppInfo\Application;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\AlreadyProcessedException;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	/** @var IFactory */
	private $l10Factory;
	/** @var IRootFolder */
	private $rootFolder;
	/** @var IURLGenerator */
	private $url;

	public function __construct(IFactory $l10Factory, IRootFolder $rootFolder, IURLGenerator $url) {
		$this->l10Factory = $l10Factory;
		$this->rootFolder = $rootFolder;
		$this->url = $url;
	}


	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10Factory->get(Application::APP_ID)->t('Files retention');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			// Not my app => throw
			throw new \InvalidArgumentException();
		}

		$userFolder = $this->rootFolder->getUserFolder($notification->getUser());

		$subject = $notification->getSubjectParameters();
		$fileId = (int)$subject['fileId'];

		$nodes = $userFolder->getById($fileId);
		if (empty($nodes)) {
			throw new AlreadyProcessedException();
		}
		$node = array_pop($nodes);

		$l = $this->l10Factory->get(Application::APP_ID, $languageCode);
		$notification->setRichSubject(
			$l->t('{file} will be removed in 24 hours'),
			[
				'file' => [
					'type' => 'file',
					'id' => $node->getId(),
					'name' => $node->getName(),
					'path' => $userFolder->getRelativePath($node->getPath()),
					'mimetype' => $node->getMimetype(),
					'link' => $this->url->linkToRouteAbsolute('files.viewcontroller.showFile', ['fileid' => $fileId]),
				],
			])
			->setParsedSubject(str_replace('{file}', $node->getName(), $l->t('{file} will be removed in 24 hours')))
			->setRichMessage(
				$l->t('Your systems retention rules will delete this file within 24 hours.')
			)
			->setParsedMessage($l->t('Your systems retention rules will delete this file within 24 hours.'))
			->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files_retention', 'app-dark.svg')));

		return $notification;
	}
}
