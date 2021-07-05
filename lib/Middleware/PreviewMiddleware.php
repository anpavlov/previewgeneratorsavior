<?php

namespace OCA\PreviewGenerator\Middleware;

use Exception;
use OCA\PreviewGenerator\Exceptions\PreviewNotGeneratedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IDBConnection;
use OCP\IPreview;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;

class PreviewMiddleware extends Middleware {

	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function beforeController($controller, $methodName){
		if ($controller instanceof \OC\Core\Controller\PreviewController) {
			$root = \OC::$server->getRootFolder();
			$userId = \OC::$server->getUserSession()->getUser()->getUID();
			$request = \OC::$server->getRequest();
			//$config = \OC::$server->getConfig();
			$userFolder = $root->getUserFolder($userId);

			if ($methodName === 'getPreview') {
				$fileId = $userFolder->get($request->getParam('file'))->getId();
			} elseif ($methodName === 'getPreviewByFileId') {
				$fileId = (int)($request->getParam('fileId'));
			}
			$qb = $this->connection->getQueryBuilder();
			$qb->select('id')
				->from('preview_generation')
				->where(
					$qb->expr()->andX(
						$qb->expr()->eq('uid', $qb->createNamedParameter($userId)),
						$qb->expr()->eq('file_id', $qb->createNamedParameter($fileId))
					)
				)->setMaxResults(1);
			$cursor = $qb->execute();
			$inTable = $cursor->fetch() !== false;
			$cursor->closeCursor();

			if ($inTable) {
				throw new PreviewNotGeneratedException();
			}
		}
	}

	public function afterException($controller, $methodName, Exception $exception) {
		if ($exception instanceof PreviewNotGeneratedException) {
			return new NotFoundResponse();
		}

		throw $exception;
	}


}

