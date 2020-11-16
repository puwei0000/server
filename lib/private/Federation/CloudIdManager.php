<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017, Robin Appelman <robin@icewind.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Robin Appelman <robin@icewind.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Federation;

use OCP\Contacts\IManager;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;

class CloudIdManager implements ICloudIdManager {
	/** @var IManager */
	private $contactsManager;

	public function __construct(IManager $contactsManager) {
		$this->contactsManager = $contactsManager;
	}

	/**
	 * @param string $cloudId
	 * @return ICloudId
	 * @throws \InvalidArgumentException
	 */
	public function resolveCloudId(string $cloudId): ICloudId {
		// TODO magic here to get the url and user instead of just splitting on @

		if (!$this->isValidCloudId($cloudId)) {
			throw new \InvalidArgumentException('Invalid cloud id');
		}

		// Find the first character that is not allowed in user names
		$id = $this->fixRemoteURL($cloudId);

		$lastAtPos = strrpos($id, '@');
		if ($lastAtPos !== false) {
			$user = substr($id, 0, $lastAtPos);
			$remote = substr($id, $lastAtPos + 1);
			if (!empty($user) && !empty($remote)) {
				return new CloudId($id, $user, $remote, $this->getDisplayNameFromContact($id));
			}
		}
		throw new \InvalidArgumentException('Invalid cloud id');
	}

	protected function getDisplayNameFromContact(string $cloudId): ?string {
		$addressBookEntries = $this->contactsManager->search($cloudId, ['CLOUD']);
		foreach ($addressBookEntries as $entry) {
			if (isset($entry['CLOUD'])) {
				foreach ($entry['CLOUD'] as $cloudID) {
					if ($cloudID === $cloudId) {
						return $entry['FN'];
					}
				}
			}
		}
		return null;
	}

	/**
	 * @param string $user
	 * @param string $remote
	 * @return CloudId
	 */
	public function getCloudId(string $user, string $remote): ICloudId {
		// TODO check what the correct url is for remote (asking the remote)
		$remote = $this->fixRemoteURL($remote);
		if (strpos($remote, 'http://') === 0) {
			$host = substr($remote, strlen('http://'));
		} elseif (strpos($remote, 'https://') === 0) {
			$host = substr($remote, strlen('https://'));
		}
		$id = $user . '@' . $host;
		return new CloudId($id, $user, $remote, $this->getDisplayNameFromContact($id));
	}

	/**
	 * Strips away a potential file names and trailing slashes:
	 * - http://localhost
	 * - http://localhost/
	 * - http://localhost/index.php
	 * - http://localhost/index.php/s/{shareToken}
	 *
	 * all return: http://localhost
	 *
	 * @param string $remote
	 * @return string
	 */
	protected function fixRemoteURL(string $remote): string {
		$remote = str_replace('\\', '/', $remote);
		if ($fileNamePosition = strpos($remote, '/index.php')) {
			$remote = substr($remote, 0, $fileNamePosition);
		}
		$remote = rtrim($remote, '/');

		return $remote;
	}

	/**
	 * @param string $cloudId
	 * @return bool
	 */
	public function isValidCloudId(string $cloudId): bool {
		return strpos($cloudId, '@') !== false;
	}
}
