<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		protected readonly IInitialState $initialState,
		protected readonly IURLGenerator $url,
		protected readonly IConfig $config,
	) {
	}

	#[\Override]
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

	#[\Override]
	public function getSection(): string {
		return 'workflow';
	}

	#[\Override]
	public function getPriority(): int {
		return 80;
	}
}
