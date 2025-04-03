<?php

require_once(__DIR__ . '/vendor/autoload.php');
import('lib.pkp.classes.plugins.GenericPlugin');
import('lib.pkp.classes.navigationMenu.NavigationMenuItem');
import('lib.pkp.classes.navigationMenu.NavigationMenuItemDAO');

require_once(__DIR__ . '/classes/main/CalidadFECYT.inc.php');

use CalidadFECYT\classes\main\CalidadFECYT;

class CalidadFECYTPlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = NULL)
    {
        $success = parent::register($category, $path, $mainContextId);
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) {
            return true;
        }
        $this->addLocaleData();

        if ($success && $this->getEnabled($mainContextId)) {
            $this->addStatsNavigationMenuItem($mainContextId);
        }
        return $success;
    }

    public function addLocaleData($locale = null)
    {
        $locale = $locale ?? AppLocale::getLocale();
        if ($localeFilenames = $this->getLocaleFilename($locale)) {
            foreach ((array) $localeFilenames as $localeFilename) {
                AppLocale::registerLocaleFile($locale, $localeFilename);
            }
            return true;
        }
        return false;
    }

    public function getName()
    {
        return 'CalidadFECYTPlugin';
    }

    public function getDisplayName()
    {
        return __('plugins.generic.calidadfecyt.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.calidadfecyt.description');
    }

    public function getActions($request, $verb)
    {
        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        return array_merge($this->getEnabled() ? array(
            new LinkAction('settings', new AjaxModal($router->url($request, null, null, 'manage', null, array(
                'verb' => 'settings',
                'plugin' => $this->getName(),
                'category' => 'generic',
            )), $this->getDisplayName()), __('manager.plugins.settings'), null)
        ) : array(), parent::getActions($request, $verb));
    }

    private function addStatsNavigationMenuItem($contextId)
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

        $navigationMenuItemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
        $navigationMenuItem = $navigationMenuItemDao->getByPath($contextId, 'fecyt-stats');
        $statsContent = $this->generateStatsContent($context);

        if (!$navigationMenuItem) {
            $navigationMenuItem = $navigationMenuItemDao->newDataObject();
            $navigationMenuItem->setPath('fecyt-stats');
            $navigationMenuItem->setType('NMI_TYPE_CUSTOM');
            $navigationMenuItem->setContextId($contextId);
            $navigationMenuItem->setTitle(__('plugins.generic.calidadfecyt.stats.menu'), 'en_US');
            $navigationMenuItem->setContent($statsContent, 'en_US');

            $navigationMenuItemId = $navigationMenuItemDao->insertObject($navigationMenuItem);

            $this->assignToNavigationMenu($navigationMenuItemId, $contextId);
        } else {
            $navigationMenuItem->setContent($statsContent, 'en_US');
            $navigationMenuItemDao->updateObject($navigationMenuItem);
        }
    }


    public function manage($args, $request)
    {
        error_log("manage() called with verb: " . $request->getUserVar('verb'));
        $this->import('classes.main.CalidadFECYT');
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $router = $request->getRouter();

        switch ($request->getUserVar('verb')) {
            case 'settings':
                $templateParams = array(
                    "journalTitle" => $context->getLocalizedName(),
                    "defaultDateFrom" => date('Y-m-d', strtotime("-1 year")),
                    "defaultDateTo" => date('Y-m-d', strtotime("-1 day")),
                    "baseUrl" => $router->url($request, null, null, 'manage', null, array(
                        'plugin' => $this->getName(),
                        'category' => 'generic'
                    ))
                );

                $calidadFECYT = new CalidadFECYT(array('request' => $request, 'context' => $context));
                $linkActions = array();
                $index = 0;
                foreach ($calidadFECYT->getExportClasses() as $export) {
                    $exportAction = new stdClass();
                    $exportAction->name = $export;
                    $exportAction->index = $index;
                    $linkActions[] = $exportAction;
                    $index++;
                }

                $templateParams['submissions'] = $this->getSubmissions($context->getId());
                $templateParams['exportAllAction'] = true;
                $templateParams['linkActions'] = $linkActions;
                $templateMgr->assign($templateParams);

                return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('settings_form.tpl')));
            case 'export':
                try {
                    $request->checkCSRF();
                    $params = array(
                        'request' => $request,
                        'context' => $context,
                        'dateFrom' => $request->getUserVar('dateFrom') ? date('Ymd', strtotime($request->getUserVar('dateFrom'))) : null,
                        'dateTo' => $request->getUserVar('dateTo') ? date('Ymd', strtotime($request->getUserVar('dateTo'))) : null
                    );
                    $calidadFECYT = new CalidadFECYT($params);
                    $calidadFECYT->export();
                } catch (Exception $e) {
                    $dispatcher = $request->getDispatcher();
                    $dispatcher->handle404();
                }
                return;
            case 'exportAll':
                try {
                    $request->checkCSRF();
                    $params = array(
                        'request' => $request,
                        'context' => $context,
                        'dateFrom' => $request->getUserVar('dateFrom') ? date('Ymd', strtotime($request->getUserVar('dateFrom'))) : null,
                        'dateTo' => $request->getUserVar('dateTo') ? date('Ymd', strtotime($request->getUserVar('dateTo'))) : null
                    );
                    $calidadFECYT = new CalidadFECYT($params);
                    $calidadFECYT->exportAll();
                } catch (Exception $e) {
                    $dispatcher = $request->getDispatcher();
                    $dispatcher->handle404();
                }
                return;
            case 'editorial':
                try {
                    $request->checkCSRF();
                    $params = array(
                        'request' => $request,
                        'context' => $context,
                        'dateFrom' => $request->getUserVar('dateFrom') ? date('Ymd', strtotime($request->getUserVar('dateFrom'))) : null,
                        'dateTo' => $request->getUserVar('dateTo') ? date('Ymd', strtotime($request->getUserVar('dateTo'))) : null
                    );
                    $calidadFECYT = new CalidadFECYT($params);
                    $calidadFECYT->editorial($request->getUserVar('submission'));
                } catch (Exception $e) {
                    $dispatcher = $request->getDispatcher();
                    $dispatcher->handle404();
                }
                return;
            default:
                $dispatcher = $request->getDispatcher();
                $dispatcher->handle404();
                return;
        }

        return parent::manage($args, $request);
    }
    private function generateStatsContent($context)
    {
        if (!$context) {
            return "<h2>FECYT Statistics</h2><p>Error: Revista no encontrada</p>";
        }

        $contextId = $context->getId();

        try {
            $currentYear = date('Y');
            $lastCompletedYear = $currentYear - 1;
            $summaryDateFrom = date('Ymd', strtotime("$lastCompletedYear-01-01"));
            $summaryDateTo = date('Ymd', strtotime("$lastCompletedYear-12-31"));

            $submissionStats = $this->getSubmissionStats($contextId, $summaryDateFrom, $summaryDateTo);
            $reviewerDetails = $this->getReviewerDetails($contextId, $summaryDateFrom, $summaryDateTo);

            $totalReceived = $submissionStats['received'];
            $totalPublished = $submissionStats['published'];
            $totalDeclined = $submissionStats['declined'];
            $rejectionRate = $totalReceived > 0 ? round(($totalDeclined / $totalReceived) * 100, 1) : 0;

            $totalReviewers = $reviewerDetails['totalReviewers'];
            $foreignReviewers = $reviewerDetails['foreignReviewers'];
            $foreignPercentage = $totalReviewers > 0 ? round(($foreignReviewers / $totalReviewers) * 100, 1) : 0;
            $statsContent = "<h2>" . sprintf(__("plugins.generic.calidadfecyt.stats.header"), $lastCompletedYear) . "</h2>";
            $statsContent .= "<p>" . sprintf(
                __("plugins.generic.calidadfecyt.stats.summary"),
                $totalReceived,
                $lastCompletedYear,
                $lastCompletedYear,
                $totalPublished,
                $rejectionRate,
                $totalReviewers,
                $foreignPercentage
            ) . "</p>";
            $statsContent .= "<h3>Reviewers</h3><ul>";
            foreach ($reviewerDetails['reviewers'] as $reviewer) {
                $statsContent .= "<li>" . htmlspecialchars($reviewer['fullName']) .
                    ($reviewer["affiliation"] && $reviewer["affiliation"] !== 'Unknown Affiliation' ? " (" . htmlspecialchars($reviewer["affiliation"]) . ")" : "") .
                    "</li>";
            }
            $statsContent .= "</ul>";

            return $statsContent;
        } catch (\Exception $e) {
            return "<h2>FECYT Statistics</h2><p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }

    private function getSubmissionStats($contextId, $dateFrom, $dateTo)
    {
        $submissionDao = \DAORegistry::getDAO('SubmissionDAO');
        $submissions = $submissionDao->getByContextId($contextId);

        $stats = ['received' => 0, 'accepted' => 0, 'declined' => 0, 'published' => 0];
        while ($submission = $submissions->next()) {
            $dateSubmitted = strtotime($submission->getDateSubmitted());
            $publication = $submission->getCurrentPublication();
            $datePublished = $publication ? strtotime($publication->getData('datePublished')) : null;
            $status = $submission->getStatus();

            if ($dateSubmitted >= strtotime($dateFrom) && $dateSubmitted <= strtotime($dateTo)) {
                $stats['received']++;
                if ($status == STATUS_PUBLISHED && $datePublished && $datePublished <= strtotime($dateTo)) {
                    $stats['accepted']++;
                    $stats['published']++;
                }
                if ($status == STATUS_DECLINED) {
                    $stats['declined']++;
                }
            }
        }
        return $stats;
    }
    private function getReviewerDetails($contextId, $dateFrom, $dateTo)
    {
        $reviewAssignmentDao = \DAORegistry::getDAO('ReviewAssignmentDAO');
        $userDao = \DAORegistry::getDAO('UserDAO');

        $reviewersResult = $reviewAssignmentDao->retrieve(
            "SELECT DISTINCT ra.reviewer_id 
             FROM review_assignments ra 
             JOIN submissions s ON ra.submission_id = s.submission_id 
             WHERE s.context_id = ? 
             AND ra.date_completed IS NOT NULL 
             AND ra.date_completed BETWEEN ? AND ?",
            [$contextId, date('Y-m-d', strtotime($dateFrom)), date('Y-m-d', strtotime($dateTo))]
        );

        $reviewers = [];
        $foreignReviewers = 0;
        $totalReviewers = 0;

        foreach ($reviewersResult as $row) {
            $reviewerId = $row->reviewer_id;
            $user = $userDao->getById($reviewerId);
            if ($user) {
                $fullName = $user->getFullName();
                $affiliation = $user->getLocalizedAffiliation() ?: 'Unknown Affiliation';
                $country = $user->getCountry() ?: 'Unknown';

                $reviewers[] = [
                    'fullName' => $fullName,
                    'affiliation' => $affiliation
                ];

                if ($country !== 'ES') {
                    $foreignReviewers++;
                }
                $totalReviewers++;
            }
        }

        return [
            'reviewers' => $reviewers,
            'foreignReviewers' => $foreignReviewers,
            'totalReviewers' => $totalReviewers
        ];
    }
    public function getSubmissions($contextId)
    {
        $locale = AppLocale::getLocale();
        $submissionDao = \DAORegistry::getDAO('SubmissionDAO');
        $query = $submissionDao->retrieve(
            "SELECT s.submission_id, pp_title.setting_value AS title
                FROM submissions s
                         INNER JOIN publications p ON p.publication_id = s.current_publication_id
                         INNER JOIN publication_settings pp_issue ON p.publication_id = pp_issue.publication_id
                         INNER JOIN publication_settings pp_title ON p.publication_id = pp_title.publication_id
                         INNER JOIN (
                    SELECT issue_id
                    FROM issues
                    WHERE journal_id = " . $contextId . "
                      AND published = 1
                    ORDER BY date_published DESC
                    LIMIT 4
                ) AS latest_issues ON pp_issue.setting_value = latest_issues.issue_id
                WHERE pp_issue.setting_name = 'issueId'
                  AND pp_title.setting_name = 'title'
                  AND pp_title.locale='" . $locale . "'"
        );

        $submissions = array();
        foreach ($query as $value) {
            $row = get_object_vars($value);
            $title = $row['title'];
            $submissions[] = [
                'id' => $row['submission_id'],
                'title' => (strlen($title) > 80) ? mb_substr($title, 0, 77, 'UTF-8') . '...' : $title,
            ];
        }
        return $submissions;
    }
}