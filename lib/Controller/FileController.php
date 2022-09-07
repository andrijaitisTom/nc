<?php

namespace OCA\NotesTutorial\Controller;

use OCA\NotesTutorial\AppInfo\Application;
use OCA\NotesTutorial\Helper;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class FileController extends Controller {
	/** @var Helper */
	private $helper;

	/** @var string */
	private $userId;

	use Errors;

	public function __construct(IRequest $request,
								Helper $helper,
								$userId) {
		parent::__construct(Application::APP_ID, $request);
		$this->helper = $helper;
		$this->userId = $userId;
	}

	/**
	 * Get files from user root
	 *
	 * @NoAdminRequired
	 */
	public function index(string $sortAttribute = 'name', bool $sortDescending = false): DataResponse {
		return new DataResponse($this->helper->getFiles("", $this->userId, $sortAttribute, $sortDescending));
	}

	public function content(string $dir, string $sortAttribute = 'name', bool $sortDescending = false): DataResponse {
		return new DataResponse($this->helper->getFiles(ltrim($dir, '/'), $this->userId, $sortAttribute, $sortDescending));
	}
}
