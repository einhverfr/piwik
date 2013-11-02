<?php
/**
 * Piwik - Open source web analytics
 *
 * @link     http://piwik.org
 * @license  http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @category Piwik_Plugins
 * @package  Dashboard
 */
namespace Piwik\Plugins\Dashboard;

use Piwik\Common;
use Piwik\DataTable\Renderer\Json;
use Piwik\Db;
use Piwik\Piwik;
use Piwik\Session\SessionNamespace;
use Piwik\View;
use Piwik\Db\Factory;
use Piwik\WidgetsList;

/**
 * Dashboard Controller
 *
 * @package Dashboard
 */
class Controller extends \Piwik\Plugin\Controller
{
    /**
     * @var Dashboard
     */
    private $dashboard;

    protected function init()
    {
        parent::init();

        $this->dashboard = new Dashboard();
    }

    protected function _getDashboardView($template)
    {
        $view = new View($template);
        $this->setGeneralVariablesView($view);

        $view->availableWidgets = Common::json_encode(WidgetsList::get());
        $view->availableLayouts = $this->getAvailableLayouts();

        $view->dashboardId = Common::getRequestVar('idDashboard', 1, 'int');
        $view->dashboardLayout = $this->getLayout($view->dashboardId);

        return $view;
    }

    public function embeddedIndex()
    {
        $view = $this->_getDashboardView('@Dashboard/embeddedIndex');

        echo $view->render();
    }

    public function index()
    {
        $view = $this->_getDashboardView('@Dashboard/index');
        $view->dashboards = array();
        if (!Piwik::isUserIsAnonymous()) {
            $login = Piwik::getCurrentUserLogin();

            $view->dashboards = $this->dashboard->getAllDashboards($login);
        }
        echo $view->render();
    }

    public function getAvailableWidgets()
    {
        $this->checkTokenInUrl();

        Json::sendHeaderJSON();
        echo Common::json_encode(WidgetsList::get());
    }

    public function getDashboardLayout()
    {
        $this->checkTokenInUrl();

        $idDashboard = Common::getRequestVar('idDashboard', 1, 'int');

        $layout = $this->getLayout($idDashboard);

        echo $layout;
    }

    /**
     * Resets the dashboard to the default widget configuration
     */
    public function resetLayout()
    {
        $this->checkTokenInUrl();
        $layout = $this->dashboard->getDefaultLayout();
        $idDashboard = Common::getRequestVar('idDashboard', 1, 'int');
        if (Piwik::isUserIsAnonymous()) {
            $session = new SessionNamespace("Dashboard");
            $session->dashboardLayout = $layout;
            $session->setExpirationSeconds(1800);
        } else {
            $this->saveLayoutForUser(Piwik::getCurrentUserLogin(), $idDashboard, $layout);
        }
    }

    /**
     * Records the layout in the DB for the given user.
     *
     * @param string $login
     * @param int $idDashboard
     * @param string $layout
     */
    protected function saveLayoutForUser($login, $idDashboard, $layout)
    {
        $UserDashboard = Factory::getDAO('user_dashboard');
        $UserDashboard->saveLayout($login, $idDashboard, $layout);
    }

    /**
     * Updates the name of a dashboard
     *
     * @param string $login
     * @param int $idDashboard
     * @param string $name
     */
    protected function updateDashboardName($login, $idDashboard, $name)
    {
        $UserDashboard = Factory::getDAO('user_dashboard');
        $UserDashboard->updateName($name, $login, $idDashboard);
    }

    /**
     * Removes the dashboard with the given id
     */
    public function removeDashboard()
    {
        $this->checkTokenInUrl();

        if (Piwik::isUserIsAnonymous()) {
            return;
        }

        $idDashboard = Common::getRequestVar('idDashboard', 1, 'int');

        // first layout can't be removed
        if ($idDashboard != 1) {
            $UserDashboard = Factory::getDAO('user_dashboard');
            $UserDashboard->deleteByLoginDashboard(
                Piwik::getCurrentUserLogin(),
                $idDashboard
            );
        }
    }

    /**
     * Outputs all available dashboards for the current user as a JSON string
     */
    public function getAllDashboards()
    {
        $this->checkTokenInUrl();

        if (Piwik::isUserIsAnonymous()) {
            Json::sendHeaderJSON();
            echo '[]';

            return;
        }

        $login = Piwik::getCurrentUserLogin();
        $dashboards = $this->dashboard->getAllDashboards($login);

        Json::sendHeaderJSON();
        echo Common::json_encode($dashboards);
    }

    /**
     * Creates a new dashboard for the current user
     * User needs to be logged in
     */
    public function createNewDashboard()
    {
        $this->checkTokenInUrl();

        if (Piwik::isUserIsAnonymous()) {
            echo '0';
            return;
        }
        $user = Piwik::getCurrentUserLogin();
        $UserDashboard = Factory::getDAO('user_dashboard');
        $nextId = $UserDashboard->getNextIdByLogin($user);

        $name = urldecode(Common::getRequestVar('name', '', 'string'));
        $type = urldecode(Common::getRequestVar('type', 'default', 'string'));
        $layout = '{}';

        if ($type == 'default') {
            $layout = $this->dashboard->getDefaultLayout();
        }

        $UserDashboard->newDashboard($user, $nextId, $name, $layout);
        echo Common::json_encode($nextId);
    }

    public function copyDashboardToUser()
    {
        $this->checkTokenInUrl();

        if (!Piwik::isUserIsSuperUser()) {
            echo '0';
            return;
        }
        $login = Piwik::getCurrentUserLogin();
        $name = urldecode(Common::getRequestVar('name', '', 'string'));
        $user = urldecode(Common::getRequestVar('user', '', 'string'));
        $idDashboard = Common::getRequestVar('dashboardId', 0, 'int');
        $layout = $this->dashboard->getLayoutForUser($login, $idDashboard);

        if ($layout !== false) {
            $UserDashboard = Factory::getDAO('user_dashboard');
            $nextId = $UserDashboard->getNextIdByLogin($user);

            $UserDashboard->newDashboard($user, $nextId, $name, $layout);

            Json::sendHeaderJSON();
            echo Common::json_encode($nextId);
            return;
        }
    }

    /**
     * Saves the layout for the current user
     * anonymous = in the session
     * authenticated user = in the DB
     */
    public function saveLayout()
    {
        $this->checkTokenInUrl();

        $layout = Common::unsanitizeInputValue(Common::getRequestVar('layout'));
        $idDashboard = Common::getRequestVar('idDashboard', 1, 'int');
        $name = Common::getRequestVar('name', '', 'string');
        if (Piwik::isUserIsAnonymous()) {
            $session = new SessionNamespace("Dashboard");
            $session->dashboardLayout = $layout;
            $session->setExpirationSeconds(1800);
        } else {
            $this->saveLayoutForUser(Piwik::getCurrentUserLogin(), $idDashboard, $layout);
            if (!empty($name)) {
                $this->updateDashboardName(Piwik::getCurrentUserLogin(), $idDashboard, $name);
            }
        }
    }

    /**
     * Saves the layout as default
     */
    public function saveLayoutAsDefault()
    {
        $this->checkTokenInUrl();

        if (Piwik::isUserIsSuperUser()) {
            $layout = Common::unsanitizeInputValue(Common::getRequestVar('layout'));
            $UserDashboard = Factory::getDAO('user_dashboard');
            $UserDashboard->saveLayout('', '1', $layout);
        }
    }

    /**
     * Get the dashboard layout for the current user (anonymous or logged user)
     *
     * @param int $idDashboard
     *
     * @return string $layout
     */
    protected function getLayout($idDashboard)
    {
        if (Piwik::isUserIsAnonymous()) {

            $session = new SessionNamespace("Dashboard");
            if (!isset($session->dashboardLayout)) {

                return $this->dashboard->getDefaultLayout();
            }

            $layout = $session->dashboardLayout;
        } else {
            $layout = $this->dashboard->getLayoutForUser(Piwik::getCurrentUserLogin(), $idDashboard);
        }

        if (!empty($layout)) {
            $layout = $this->dashboard->removeDisabledPluginFromLayout($layout);
        }

        if (empty($layout)) {
            $layout = $this->dashboard->getDefaultLayout();
        }

        return $layout;
    }

    /**
     * Returns all available column layouts for the dashboard
     *
     * @return array
     */
    protected function getAvailableLayouts()
    {
        return array(
            array(100),
            array(50, 50), array(67, 33), array(33, 67),
            array(33, 33, 33), array(40, 30, 30), array(30, 40, 30), array(30, 30, 40),
            array(25, 25, 25, 25)
        );
    }
}


