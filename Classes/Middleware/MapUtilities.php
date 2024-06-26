<?php

namespace WapplerSystems\Map\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use \TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Http\Response;


class MapUtilities implements MiddlewareInterface
{

    public function process(
        ServerRequestInterface  $request,
        RequestHandlerInterface $handler
    ): ResponseInterface
    {

        /** @var NormalizedParams $normalizedParams */
        $normalizedParams = $request->getAttribute('normalizedParams');
        $typo3SiteUrl = $normalizedParams->getSiteUrl(); // Same as GeneralUtility::getIndpEnv('TYPO3_SITE_URL')

        $requestArguments = $request->getParsedBody()['tx_mapmagic_ajax'] ?? [];

        // Remove any output produced until now
        ob_clean();

        // continue only if action is ajaxPsr of extension map_magic
        if (!isset($requestArguments['action']) || $requestArguments['action'] != 'ajaxPsr') return $handler->handle($request);

        $ajaxController = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('WapplerSystems\Map\Controller\AjaxController');

        $response = GeneralUtility::makeInstance(Response::class);
        $response->withHeader('Content-type', ['text/html; charset=UTF-8']);

        $out = $ajaxController->indexAction($request, $response);

        $response->getBody()->write($out);

        return $response;
    }


}
