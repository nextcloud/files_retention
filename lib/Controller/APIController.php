<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_Retention\Controller;

use OCA\Files_Retention\Constants;
use OCA\Files_Retention\Exception\AddRuleException;
use OCA\Files_Retention\ResponseDefinitions;
use OCA\Files_Retention\Service\RetentionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * @psalm-import-type Files_RetentionRule from ResponseDefinitions
 */
class APIController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly RetentionService $retentionService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * List retention rules
	 *
	 * @return DataResponse<Http::STATUS_OK, list<Files_RetentionRule>, array{}>
	 *
	 * 200: List retention rules
	 */
	public function getRetentions(): DataResponse {
		$retentions = $this->retentionService->getRetentions();

		return new DataResponse($retentions);
	}

	/**
	 * Create a retention rule
	 *
	 * @param int $tagid Tag the retention is based on
	 * @param 0|1|2|3 $timeunit Time unit of the retention (days, weeks, months, years)
	 * @psalm-param Constants::UNIT_* $timeunit
	 * @param positive-int $timeamount Amount of time units that have to be passed
	 * @param 0|1 $timeafter Whether retention time is based creation time (0) or modification time (1)
	 * @psalm-param Constants::MODE_* $timeafter
	 * @return DataResponse<Http::STATUS_BAD_REQUEST, array{error: 'tagid'|'timeunit'|'timeamount'|'timeafter'}, array{}>|DataResponse<Http::STATUS_CREATED, Files_RetentionRule, array{}>
	 *
	 * 201: Retention rule created
	 * 400: At least one of the parameters was invalid
	 */
	public function addRetention(int $tagid, int $timeunit, int $timeamount, int $timeafter = Constants::MODE_CTIME): DataResponse {
		try {
			$id = $this->retentionService->addRetention($tagid, $timeunit, $timeamount, $timeafter);
		} catch (AddRuleException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse([
			'id' => $id,
			'tagid' => $tagid,
			'timeunit' => $timeunit,
			'timeamount' => $timeamount,
			'timeafter' => $timeafter,
			'hasJob' => true,
		], Http::STATUS_CREATED);
	}

	/**
	 * Delete a retention rule
	 *
	 * @param int $id Retention rule to delete
	 * @return DataResponse<Http::STATUS_NO_CONTENT|Http::STATUS_NOT_FOUND, list<empty>, array{}>
	 *
	 * 204: Retention rule deleted
	 * 404: Retention rule not found
	 */
	public function deleteRetention(int $id): DataResponse {
		$removed = $this->retentionService->deleteRetention($id);

		return new DataResponse([], $removed === false ? Http::STATUS_NOT_FOUND : Http::STATUS_NO_CONTENT);
	}
}
