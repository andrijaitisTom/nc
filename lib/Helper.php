<?php
/**
 * Based on nextcloud/apps/files/lib/Helper.php by multiples authors and
 * somewhat stripped and modified
 *
 * @author Romain Lebrun Thauront <romain@framasoft.org>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\NotesTutorial;

use OCP\Files\IRootFolder;
use OCP\Files\FileInfo;
use OCP\ITagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\ISystemTagManager;

/**
 * Helper class for manipulating file information
 */
class Helper {
	/** @var IRootFolder */
	private $storage;

	/** @var ITagManager */
	private $tagManager;
	
	/** @var ISystemTagObjectMapper */
	private $tagObjectMapper;

	/** @var ISystemTagManager */
	private $systemTagManager;

	public function __construct(
		IRootFolder $storage,
		ITagManager $tagManager,
		ISystemTagObjectMapper $tagObjectMapper,
		ISystemTagManager $systemTagManager
	) {
		$this->storage = $storage;
		$this->tagManager = $tagManager;
		$this->tagObjectMapper = $tagObjectMapper;
		$this->systemTagManager = $systemTagManager;
	}

	public static function concatenate_callback($carry, $item) {
		$carry .= $item;
		return $carry;
	}

	/**
	 * @param string $dir
	 * @return array
	 * @throws \OCP\Files\NotFoundException
	 */
	public function buildFileStorageStatistics($dir) {
		// information about storage capacities
		$storageInfo = \OC_Helper::getStorageInfo($dir);
		$l = \OC::$server->getL10N('files');
		$maxUploadFileSize = \OCP\Util::maxUploadFilesize($dir, $storageInfo['free']);
		$maxHumanFileSize = \OCP\Util::humanFileSize($maxUploadFileSize);
		$maxHumanFileSize = $l->t('Upload (max. %s)', [$maxHumanFileSize]);

		return [
			'uploadMaxFilesize' => $maxUploadFileSize,
			'maxHumanFilesize' => $maxHumanFileSize,
			'freeSpace' => $storageInfo['free'],
			'quota' => $storageInfo['quota'],
			'used' => $storageInfo['used'],
			'usedSpacePercent' => (int)$storageInfo['relative'],
			'owner' => $storageInfo['owner'],
			'ownerDisplayName' => $storageInfo['ownerDisplayName'],
			'mountType' => $storageInfo['mountType'],
			'mountPoint' => $storageInfo['mountPoint'],
		];
	}

	/**
	 * Comparator function to sort files alphabetically and have
	 * the directories appear first
	 *
	 * @param string[] $a file
	 * @param string[] $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public function compareFileOrFolder(array $a, array $b) {
		$aType = $a["type"];
		$bType = $b["type"];
		if ($aType === 'dir' and $bType !== 'dir') {
			return -1;
		} elseif ($aType !== 'dir' and $bType === 'dir') {
			return 1;
		} else {
			return 0;
		}
	}

	/**
	 * Comparator function to sort files alphabetically and have
	 * the directories appear first
	 *
	 * @param string[] $a file
	 * @param string[] $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public function compareFileNames(array $a, array $b) {
		$aName = $a["nodeName"];
		$bName = $b["nodeName"];
		return \OCP\Util::naturalSortCompare($aName, $bName);
	}

	/**
	 * Comparator function to sort files by date
	 *
	 * @param string[] $a file
	 * @param string[] $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public function compareTimestamp(array $a, array $b) {
		$aTime = $a["mtime"];
		$bTime = $b["mtime"];
		return ($aTime < $bTime) ? -1 : 1;
	}

	/**
	 * Comparator function to sort files by size
	 *
	 * @param string[] $a file
	 * @param string[] $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public function compareSize(array $a, array $b) {
		$aSize = $a["size"];
		$bSize = $b["size"];
		return ($aSize < $bSize) ? -1 : 1;
	}

	/**
	 * Comparator function to sort files by tags
	 *
	 * @param string[] $a file
	 * @param string[] $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public function compareTags(array $a, array $b) {
		$aTags = array_reduce($a["tags"], [Helper::class, "concatenate_callback"]);
		$bTags = array_reduce($b["tags"], [Helper::class, "concatenate_callback"]);
		return \OCP\Util::naturalSortCompare($aTags, $bTags);
	}

	/**
	 * Comparator function to sort files by tags
	 *
	 * @param string[] $a file
	 * @param string[] $b file
	 * @return int -1 if $a must come before $b, 1 otherwise
	 */
	public function compareSystemTags(array $a, array $b) {
		$aTags = array_reduce($a["systemTags"], [Helper::class, "concatenate_callback"]);
		$bTags = array_reduce($b["systemTags"], [Helper::class, "concatenate_callback"]);
		return \OCP\Util::naturalSortCompare($aTags, $bTags);
	}

	/**
	 *
	 * Retrieves the contents of the given directory and returns it as a sorted
	 * array of json ready fileList, populate with Tags and SystemTags
	 *
	 * @param string $dir path to the directory
	 * @param string $userId current user's id
	 * @param string $sortAttribute attribute to sort on
	 * @param bool $sortDescending true for descending sort, false otherwise
	 * @param bool $folderFirst true for having folders on top of files, false otherwise
	 * @return array fileList
	 */
	public function getFiles($dir, $userId, $sortAttribute = 'name', $sortDescending = false, $folderFirst = true) {
		$userFolder = $this->storage->getUserFolder($userId);
		try {
			$folder = $userFolder->get($dir);
			if ($folder instanceof \OCP\Files\Folder) {
				$nodes = $folder->getDirectoryListing();
				$content = $this->nodesToArray($nodes);
				return $this->sortFiles($content, $sortAttribute, $sortDescending, $folderFirst);
			} else {
				throw new StorageException('Can not read from folder');
			}
		} catch (\OCP\Files\NotFoundException $e) {
			throw new StorageException('Folder does not exist');
		}
	}

	/**
	 * Populate the result set with file tags
	 *
	 * @param array $fileList
	 * @param string $fileIdentifier identifier attribute name for values in $fileList
	 * @return array file list populated with tags
	 */
	public function populateTags(array $fileList, $fileIdentifier) {
		$ids = [];
		foreach ($fileList as $fileData) {
			$ids[] = $fileData[$fileIdentifier];
		}
		$tagger = $this->tagManager->load('files');
		$tags = $tagger->getTagsForObjects($ids);

		if (!is_array($tags)) {
			throw new \UnexpectedValueException('$tags must be an array');
		}

		// Set empty tag array
		foreach ($fileList as $key => $fileData) {
			$fileList[$key]['tags'] = [];
		}

		if (!empty($tags)) {
			foreach ($tags as $fileId => $fileTags) {
				foreach ($fileList as $key => $fileData) {
					if ($fileId !== $fileData[$fileIdentifier]) {
						continue;
					}

					$fileList[$key]['tags'] = $fileTags;
				}
			}
		}

		return $fileList;
	}

	/**
	 * Populate the result set with file systems tags
	 *
	 * @param array $fileList
	 * @param string $fileIdentifier identifier attribute name for values in $fileList
	 * @return array file list populated with tags
	 */
	public function populateSystemTags(array $fileList, $fileIdentifier) {
		$ids = [];
		foreach ($fileList as $fileData) {
			$ids[] = $fileData[$fileIdentifier];
		}
		$systemTags = $this->tagObjectMapper->getTagIdsForObjects($ids, "files");

		if (!is_array($systemTags)) {
			throw new \UnexpectedValueException('$systemTags must be an array');
		}

		// Set empty tag array
		foreach ($fileList as $key => $fileData) {
			$fileList[$key]['systemTags'] = [];
		}

		if (!empty($systemTags)) {
			foreach ($systemTags as $fileId => $fileTags) {
				foreach ($fileList as $key => $fileData) {
					if ($fileId !== $fileData[$fileIdentifier]) {
						continue;
					}
					$tagName = [];
					foreach ($this->systemTagManager->getTagsByIds($fileTags) as $tagObject) {
						$tagName[] = $tagObject->getName();
					}
					$fileList[$key]['systemTags'] = $tagName ;
				}
			}
		}

		return $fileList;
	}

	/**
	 * Sort the given file info array
	 *
	 * @param array $fileList files to sort
	 * @param string $sortAttribute attribute to sort on
	 * @param bool $sortDescending true for descending sort, false otherwise
	 * @return array Sorted files
	 */
	public function sortFiles($fileList, $sortAttribute = 'name', $sortDescending = false, $folderFirst = true) {
		switch ($sortAttribute) {
			case 'name':
				$sortFunc = 'compareFileNames';
				break;
			case 'mtime':
				$sortFunc = 'compareTimestamp';
				break;
			case 'size':
				$sortFunc = 'compareSize';
				break;
			case 'tags':
			case 'favorite':
				if (!array_key_exists('tags', $fileList)) {
					$fileList = $this->populateTags($fileList, "id");
				}
				$sortFunc = 'compareTags';
				break;
			case 'systemtags':
				if (!array_key_exists('systemTags', $fileList)) {
					$fileList = $this->populateTags($fileList, "id");
				}
				$sortFunc = 'compareSystemTags';
				break;
		}
		usort($fileList, [Helper::class, $sortFunc]);
		if ($folderFirst) {
			usort($fileList, [Helper::class, "compareFileOrFolder"]);
		}
		if ($sortDescending) {
			$fileList = array_reverse($fileList);
		}
		return $fileList;
	}

	/**
	 * Json serialize FileInfos
	 *
	 * @param FileInfo[] $files FileInfos to serialize
	 *
	 * @return array Files metadata
	 */
	public function nodesToArray($files) {
		$nodes = [];
		foreach ($files as $node_info) {
			$nodes[] = [
				'id' => $node_info->getId(),
				'nodeName' => $node_info->getName(),
				'path' => preg_replace('/^(\/[^\/]*){2}/', '', $node_info->getPath()),
				'size' => $node_info->getSize(),
				'mtime' => $node_info->getMTime(),
				'type' => $node_info->getType(),
				'mimetype' => $node_info->getMimetype(),
				'isShared' => $node_info->isShared(),
			];
		}

		$nodes = $this->populateTags($nodes, "id");
		$nodes = $this->populateSystemTags($nodes, "id");
		return $nodes;
	}
}
