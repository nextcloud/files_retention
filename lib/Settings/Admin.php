<?php

declare(strict_types=1);
/**
 * @copyright 2016 Roeland Jago Douma <roeland@famdouma.nl>
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
namespace OCA\Files_Retention\Settings;

use OCA\Files_Retention\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\Settings\ISettings;
use OCP\Util;

class Admin implements ISettings {
	public function __construct(
		protected IInitialState $initialState,
		protected IURLGenerator $url,
		protected IConfig $config,
	) {
	}

	public function getForm(): TemplateResponse {
		Util::addScript('files_retention', 'files_retention-main');

		$this->initialState->provideInitialState(
			'doc-url',
			$this->url->linkToDocs('admin-files-retention')
		);

		$this->initialState->provideInitialState(
			'notify_before',
			$this->config->getAppValue(Application::APP_ID, 'notify_before', 'no') === 'yes'
		);

		return new TemplateResponse('files_retention', 'admin', [], '');
	}

	public function getSection(): string {
		return 'workflow';
	}

	public function getPriority(): int {
		return 80;
	}
}
