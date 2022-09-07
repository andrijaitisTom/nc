<?php

return [
	'resources' => [
		'note' => ['url' => '/notes'],
		'note_api' => ['url' => '/api/0.1/notes'],
		'agreement' => ['url' => '/agreements'],
		'agreement_api' => ['url' => '/api/0.1/agreements'],
	],
	'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'note_api#preflighted_cors', 'url' => '/api/0.1/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],
		['name' => 'agreement_api#preflighted_cors', 'url' => '/api/0.1/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']],

			
			['name' => 'page#index', 'url' => '/files', 'verb' => 'GET', 'postfix' => 'files'],
			['name' => 'page#index', 'url' => '/filters', 'verb' => 'GET', 'postfix' => 'filters'],
			['name' => 'file#index', 'url' => '/nodelist', 'verb' => 'GET'],
			['name' => 'file#content', 'url' => '/nodelist/{dir}', 'verb' => 'GET', 'requirements' => ['dir' => '.+']],
			['name' => 'filter#search', 'url' => '/search', 'verb' => 'POST'],


	]


	
];
