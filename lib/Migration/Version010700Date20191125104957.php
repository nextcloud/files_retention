<?php

declare(strict_types=1);

namespace OCA\Files_Retention\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010700Date20191125104957 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('retention')) {
			$table = $schema->createTable('retention');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('tag_id', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('time_unit', 'integer', [
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('time_amount', 'smallint', [
				'notnull' => true,
				'length' => 1,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['tag_id'], 'retention_tag');
		}
		return $schema;
	}
}
