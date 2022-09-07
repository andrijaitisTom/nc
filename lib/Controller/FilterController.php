<?php

namespace OCA\NotesTutorial\Controller;

use OCA\NotesTutorial\AppInfo\Application;
use OCA\NotesTutorial\Filter\FilterManager;
use OCA\NotesTutorial\Helper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class FilterController extends Controller {
	/** @var string */
	private $userId;

	/** @var FilterManager */
	private $filterManager;

	/** @var Helper */
	private $helper;

	use Errors;

	public function __construct(IRequest $request, $userId, FilterManager $filterManager, Helper $helper) {
		parent::__construct(Application::APP_ID, $request);
		$this->userId = $userId;
		$this->filterManager = $filterManager;
		$this->helper = $helper;
	}

	/**
	 * Get files matching provided filters
	 *
	 * @NoAdminRequired
	 */
	public function search(array $filters, string $sortField = "names", string $direction = "asc", int $paginationOffset = 0, int $paginationLimit = 0): DataResponse {
		$nodes = $this->filterManager->resolveFilters($filters, $this->userId, $sortField, $direction, $paginationOffset, $paginationLimit);
		return new DataResponse($this->helper->nodesToArray($nodes));
	}
}
