<?php
/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Causal\Oidc\Report\Status;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * Performs some checks about the OAuth client status.
 */
class OAuthClientStatus implements \TYPO3\CMS\Reports\StatusProviderInterface
{

    /**
     * Compiles a collection of system status checks as a status report.
     *
     * @return array An array of \TYPO3\CMS\Reports\Status objects
     * @see \TYPO3\CMS\Reports\StatusProviderInterface::getStatus()
     */
    public function getStatus()
    {
        $statusReports = [];
        $oauthApplications = BackendUtility::getRecordsByField(
            'tx_oidc_application',
            'pid',
            0
        );

        /** @var \TYPO3\CMS\Reports\Status $statusReport */
        $numberOfApplications = count($oauthApplications);
        $statusReport = GeneralUtility::makeInstance(\TYPO3\CMS\Reports\Status::class,
            $this->translate('configuration.records.title'),
            sprintf($this->translate('configuration.records.value'), $numberOfApplications),
            null,
            $numberOfApplications ? \TYPO3\CMS\Reports\Status::NOTICE : \TYPO3\CMS\Reports\Status::WARNING
        );
        $statusReports[] = $statusReport;

        if (!empty($oauthApplications)) {
            foreach ($oauthApplications as $key => $oauthApplication) {
                $statusReports[] = $this->getStatusReport($oauthApplication);
            }
        }

        return $statusReports;
    }

    /**
     * Returns a status report for given OAuth application record.
     *
     * @param array $row
     * @return \TYPO3\CMS\Reports\Status
     */
    protected function getStatusReport(array $row)
    {
        if (empty($row['oauth_client_key'])
            || empty($row['oauth_client_secret'])
            || empty($row['endpoint_authorize'])
            || empty($row['endpoint_token'])
        ) {
            $status = \TYPO3\CMS\Reports\Status::ERROR;
            $value = $this->translate('configuration.incomplete.title');
            $message = $this->translate('configuration.incomplete.message');
        } else {
            // Application is configured, check if authencated

            if (empty($row['access_token'])) {
                /** @var \Causal\Oidc\Service\OAuthService $service */
                $service = GeneralUtility::makeInstance(\Causal\Oidc\Service\OAuthService::class)
                    ->setApplication($row);
                $authorizationUrl = $service->getAuthorizationUrl();

                // Store the state into the database
                $state = $service->getState();
                static::getDatabaseConnection()->exec_UPDATEquery(
                    'tx_oidc_application',
                    'uid=' . (int)$row['uid'],
                    [
                        'state' => $state,
                    ]
                );

                $status = \TYPO3\CMS\Reports\Status::WARNING;
                $value = $this->translate('configuration.unauthorized.value');
                $link = sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    htmlspecialchars($authorizationUrl),
                    $this->translate('configuration.unauthorized.message.link')
                );
                $message = sprintf($this->translate('configuration.unauthorized.message'), $link);
            } else {
                $status = \TYPO3\CMS\Reports\Status::OK;
                $value = 'that\'s good!';
                $message = '';
            }
        }

        /** @var \TYPO3\CMS\Reports\Status $statusReport */
        $statusReport = GeneralUtility::makeInstance(\TYPO3\CMS\Reports\Status::class,
            sprintf('%s (#%s)', $row['name'], $row['uid']),
            $value,
            $message,
            $status
        );

        return $statusReport;
    }

    /**
     * Returns a translated label.
     *
     * @param string $key
     * @param bool $hsc
     * @return string
     */
    protected function translate($key, $hsc = true)
    {
        $label = $GLOBALS['LANG']->sL('LLL:EXT:oidc/Resources/Private/Language/locallang_reports.xlf:' . $key, $hsc);
        return $label;
    }

    /**
     * Returns the database connection.
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected static function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

}
