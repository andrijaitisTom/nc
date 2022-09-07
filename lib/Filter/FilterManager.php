<?php

namespace OCA\NotesTutorial\Filter;

use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchOrder;
use OC\Files\Search\SearchQuery;

use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;
use OCP\Files\IRootFolder;

class FilterManager {
	/** @var IRootFolder */
	private $storage;

	/** @var ISystemTagObjectMapper */
	private $systemTagObjectMapper;

	/** @var IFilterBuilder[] */
	private $builderArray;

	public function __construct(
		IRootFolder $storage,
		ISystemTagObjectMapper $systemTagObjectMapper
	) {
		$this->storage = $storage;
		$this->systemTagObjectMapper = $systemTagObjectMapper;
	}

	/**
	 * Returns an array of nodes matching all filters parameter, joined with
	 * 'AND'.
	 *
	 * @param array $filters [[ISearchComparison::COMPARATOR_*, type, searchString], ...]
	 * @param string $userID
	 * @param string $sortField
	 * @param string $direction
	 * @param int $paginationLimit
	 * @param int $paginationOffset
	 * @param bool $limitToHome
	 *
	 * @return FileInfo[] Nodes matching the filters and pagination
	 *
	 */
	public function resolveFilters(
		array $filters,
		string $userId,
		string $sortField = "names",
		string $direction = "asc",
		int $paginationOffset = 0,
		int $paginationLimit = 20,
		bool $limitToHome = true
	) {
		// xdebug_break();

		$userFolder = $this->storage->getUserFolder($userId);
		$searchOperator = null;
		$systemTags = [];
		$filtersType = [
			"hasFilesApi" => false,
			"hasSystemtagApi" => false,
		];

		// FILTERS SEPARATION AND TREATMENT
		foreach ($filters as $filter) {
			switch ($filter[1]) {
			case "systemtags": // the filter belong to 'Systemtag' API
				$filtersType["hasSystemtagApi"] = true;
				$systemTags = array_merge($systemTags, $filter[2]);
				break;
			default: // the filter belong to 'Files' API
				$filtersType["hasFilesApi"] = true;
				if (count($filter) !== 3) {
					throw new \InvalidArgumentException("Provided filter has not a valid number of arguments");
				}
				if ($filter[0] === ISearchComparison::COMPARE_LIKE || $filter[0] === ISearchComparison::COMPARE_LIKE_CASE_SENSITIVE) {
					$filter[2] = '%'.$filter[2].'%';
				}

				$comparison = new SearchComparison($filter[0], $filter[1], $filter[2]);
				if ($searchOperator === null) {
					$searchOperator = $comparison;
				} else {
					$searchOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [$searchOperator, $comparison]);
				}

			}
		}

		// REQUEST API FOR FILTERED NODES
		if ($filtersType['hasSystemtagApi']) {
			$paginationLimit = 0;
			$paginationOffset = 0;
			$systemTags = array_unique($systemTags);
			$systemTagNodesIds = $this->systemTagObjectMapper->getObjectIdsForTags($systemTags, "files", 0);
		}

		if ($filtersType['hasFilesApi']) {
			$searchQuery = new SearchQuery(
				$searchOperator,
				$paginationLimit,
				$paginationOffset,
				[new SearchOrder($direction, $sortField)],
				$userFolder->getOwner(),
				$limitToHome
			);
			$filesNodes = $userFolder->search($searchQuery);
		}

		// MERGE RESULTS AND RETURN

		if ($filtersType['hasSystemtagApi'] && !$filtersType['hasFilesApi']) {
			$nodes = [];
			foreach ($systemTagNodesIds as $nodeId) {
				$nodes[] = $userFolder->getById($nodeId)[0];
			}
			return $nodes;
		} elseif ($filtersType['hasFilesApi'] && !$filtersType['hasSystemtagApi']) {
			return $filesNodes;
		} else {
			$filesNodesIds = array_map(function ($node) {
				return $node->getId();
			}, $filesNodes);

			$resultsIds = array_intersect($filesNodesIds, $systemTagNodesIds);

			foreach ($filesNodes as $node) {
				if (in_array($node->getId(), $resultsIds)) {
					$nodes[] = $node;
				}
			}

			return $nodes;
		}
	}
}
