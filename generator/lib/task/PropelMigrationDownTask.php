<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

require_once dirname(__FILE__) . '/BasePropelMigrationTask.php';
require_once dirname(__FILE__) . '/../util/PropelMigrationManager.php';

/**
 * This Task executes the next migration down
 *
 * @author     Francois Zaninotto
 * @package    propel.generator.task
 */
class PropelMigrationDownTask extends BasePropelMigrationTask
{

	/**
	 * Main method builds all the targets for a typical propel project.
	 */
	public function main() {
		// Down specified count migrations.
		$count = (int)$this->project->getProperty('count');
		if ($count < 1) {
			$count = 1;
		}

		$manager = new PropelMigrationManager();
		$manager->setConnections($this->getGeneratorConfig()->getBuildConnections());
		$manager->setMigrationTable($this->getMigrationTable());
		$manager->setMigrationDir($this->getOutputDirectory());

		$previousTimestamps = $manager->getAlreadyExecutedMigrationTimestamps();

		$countMigrations = count($previousTimestamps);
		if ($count > $countMigrations) {
			$count = $countMigrations;
		}

		$countMigrationDown = 0;
		for ($i = 0; $i < $count; $i++) {
			if (!$nextMigrationTimestamp = array_pop($previousTimestamps)) {
				$this->log('No migration were ever executed on this database - nothing to reverse.');

				break;
			}

			// Save current list timestamps
			$tmpTimestamps = $previousTimestamps;

			$this->log(sprintf(
				'Executing migration %s down',
				$manager->getMigrationClassName($nextMigrationTimestamp)
			));

			if ($nbPreviousTimestamps = count($tmpTimestamps)) {
				$previousTimestamp = array_pop($tmpTimestamps);
			} else {
				$previousTimestamp = 0;
			}

			$migration = $manager->getMigrationObject($nextMigrationTimestamp);
			if (false === $migration->preDown($manager)) {
				$this->log('preDown() returned false. Aborting migration.', Project::MSG_ERR);

				break;
			}

			// Down SQL
			if (!$this->sqlDown($manager, $migration, $nextMigrationTimestamp, $previousTimestamp)) {
				break;
			}

			$migration->postDown($manager);

			if ($nbPreviousTimestamps) {
				$this->log(sprintf('Reverse migration complete. %d more migrations available for reverse.', $nbPreviousTimestamps));
			} else {
				$this->log('Reverse migration complete. No more migration available for reverse');
			}

			$countMigrationDown++;
		}

		$this->log(sprintf('Count migration downgrade: %d of %d', $countMigrationDown, $count));

		if ($countMigrationDown === $count) {
			$ret = true;
		} else {
			$this->log('Not all migration downgrade!', Project::MSG_ERR);
			$ret = false;
		}

		return $ret;
	}

	/**
	 * @param PropelMigrationManager $manager
	 * @param $migration
	 * @param int $nextMigrationTimestamp
	 * @param int $previousTimestamp
	 *
	 * @return bool
	 */
	private function sqlDown($manager, $migration, $nextMigrationTimestamp, $previousTimestamp)
	{
		$ret = true;
		try {
			foreach ($migration->getDownSQL() as $datasource => $sql) {
				$connection = $manager->getConnection($datasource);
				$this->log(sprintf(
					'Connecting to database "%s" using DSN "%s"',
					$datasource,
					$connection['dsn']
				), Project::MSG_VERBOSE);
				$pdo = $manager->getPdoConnection($datasource);
				$res = 0;
				$statements = PropelSQLParser::parseString($sql);
				foreach ($statements as $statement) {
					try {
						$this->log(sprintf('Executing statement "%s"', $statement), Project::MSG_VERBOSE);
						$stmt = $pdo->prepare($statement);
						$stmt->execute();
						$res++;
					} catch (PDOException $e) {
						$this->log(sprintf('Failed to execute SQL "%s"', $statement), Project::MSG_ERR);
						// continue
					}
				}
				if (!$res) {
					$this->log('No statement was executed. The version was not updated.');
					$this->log(sprintf(
						'Please review the code in "%s"',
						$manager->getMigrationDir() . DIRECTORY_SEPARATOR . $manager->getMigrationClassName($nextMigrationTimestamp)
					));
					$this->log('Migration aborted', Project::MSG_ERR);

					throw new Exception('Migration aborted');
				}
				$this->log(sprintf(
					'%d of %d SQL statements executed successfully on datasource "%s"',
					$res,
					count($statements),
					$datasource
				));

				$manager->updateLatestMigrationTimestamp($datasource, $previousTimestamp);
				$this->log(sprintf(
					'Downgraded migration date to %d for datasource "%s"',
					$previousTimestamp,
					$datasource
				), Project::MSG_VERBOSE);
			}

		} catch(Exception $e) {
			$ret = false;
		}

		return $ret;
	}
}
