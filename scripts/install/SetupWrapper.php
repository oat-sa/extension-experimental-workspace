<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2015 (original work) Open Assessment Technologies SA;
 *
 *
 */
namespace oat\taoWorkspace\scripts\install;

use common_persistence_Manager;
use core_kernel_persistence_smoothsql_SmoothModel;
use oat\generis\model\data\ModelManager;
use oat\generis\model\kernel\persistence\smoothsql\search\ComplexSearchService;
use oat\oatbox\extension\InstallAction;
use oat\tao\model\lock\LockManager;
use oat\tao\model\Tree\GenerisTreeFactoryBuilderService;
use oat\taoRevision\model\Repository;
use oat\taoRevision\model\RepositoryService;
use oat\taoWorkspace\model\generis\WrapperModel;
use oat\taoWorkspace\model\lockStrategy\LockSystem;
use oat\taoWorkspace\model\lockStrategy\SqlStorage;
use oat\taoWorkspace\model\RevisionWrapper;

/**
 * @author Joel Bout <joel@taotesting.com>
 */
class SetupWrapper extends InstallAction
{
    /**
     * @param $params
     * @throws \common_Exception
     * @throws \common_exception_InconsistentData
     * @throws \oat\oatbox\service\exception\InvalidServiceManagerException
     */
    public function __invoke($params)
    {
        SqlStorage::createTable();

        $code = 666;
        $persistence = ModelManager::getModel()->getPersistence();

        /** @var common_persistence_Manager $pm */
        $pm = $this->getServiceManager()->get(common_persistence_Manager::SERVICE_ID);
        foreach ($pm->getOption(common_persistence_Manager::OPTION_PERSISTENCES) as $k => $p) {
            if ($persistence == $pm->getPersistenceById($k)){
                break;
            }
        }

        $workspaceModel = new core_kernel_persistence_smoothsql_SmoothModel(array(
            core_kernel_persistence_smoothsql_SmoothModel::OPTION_PERSISTENCE => $k,
            core_kernel_persistence_smoothsql_SmoothModel::OPTION_READABLE_MODELS => array($code),
            core_kernel_persistence_smoothsql_SmoothModel::OPTION_WRITEABLE_MODELS => array($code),
            core_kernel_persistence_smoothsql_SmoothModel::OPTION_NEW_TRIPLE_MODEL => $code,
            core_kernel_persistence_smoothsql_SmoothModel::OPTION_SEARCH_SERVICE => ComplexSearchService::SERVICE_ID,
        ));
        
        $model = ModelManager::getModel();
        $model->setOption(core_kernel_persistence_smoothsql_SmoothModel::OPTION_SEARCH_SERVICE , ComplexSearchService::SERVICE_ID);
        
        $wrapedModel = WrapperModel::wrap($model, $workspaceModel );
        $wrapedModel->setServiceLocator($this->getServiceLocator());
        ModelManager::setModel($wrapedModel);

        LockManager::setImplementation(new LockSystem());

        $oldRepository = $this->getServiceManager()->get(Repository::SERVICE_ID);
        $this->registerService('taoWorkspace/innerRevision', $oldRepository);

        $newService = new RevisionWrapper(array(
            RevisionWrapper::OPTION_INNER_IMPLEMENTATION => 'taoWorkspace/innerRevision',
            RepositoryService::OPTION_FS => 'revisions'
        ));
        $this->registerService(Repository::SERVICE_ID, $newService);


        /** @var GenerisTreeFactoryBuilderService $generisTreeBuilder */
        $generisTreeBuilder = $this->getServiceLocator()->get(GenerisTreeFactoryBuilderService::SERVICE_ID);
        $generisTreeBuilder->setOption(GenerisTreeFactoryBuilderService::OPTION_SHOW_NO_LABEL_RESOURCES, false);

        $this->getServiceManager()->register(GenerisTreeFactoryBuilderService::SERVICE_ID, $generisTreeBuilder);
    }
}
