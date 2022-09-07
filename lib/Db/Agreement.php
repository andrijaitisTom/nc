<?php

namespace OCA\NotesTutorial\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;

class Agreement extends Entity implements JsonSerializable {
	
	protected $title;
	protected $content;
	protected $userId;
	protected $physical;
	protected $namelt;
	protected $idfile;

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'content' => $this->content,
			'physical' => $this->physical,
			'namelt' => $this->namelt,
			'idfile' => $this->idfile
		];
	}
}
