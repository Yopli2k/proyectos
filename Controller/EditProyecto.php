<?php
/**
 * This file is part of Proyectos plugin for FacturaScripts
 * Copyright (C) 2020-2021 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace FacturaScripts\Plugins\Proyectos\Controller;

use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Lib\ExtendedController\EditController;
use FacturaScripts\Core\Lib\ExtendedController\EditView;
use FacturaScripts\Dinamic\Lib\ProjectStockManager;

/**
 * Description of EditProyecto
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class EditProyecto extends EditController
{

    /**
     * 
     * @return string
     */
    public function getModelClassName()
    {
        return 'Proyecto';
    }

    /**
     * Returns basic page attributes
     *
     * @return array
     */
    public function getPageData()
    {
        $data = parent::getPageData();
        $data['menu'] = 'projects';
        $data['title'] = 'project';
        $data['icon'] = 'fas fa-folder-open';
        $data['showonmenu'] = false;
        return $data;
    }

    protected function createViews()
    {
        parent::createViews();

        /// disable company column if there is only one company
        if ($this->empresa->count() < 2) {
            $this->views[$this->getMainViewName()]->disableColumn('company');
        }

        $this->createViewsTasks();
        $this->createViewsNotes();
        $this->createViewsStock();
        $this->createViewsBusinessDocument('PresupuestoProveedor', 'supplier-estimations');
        $this->createViewsBusinessDocument('PedidoProveedor', 'supplier-orders');
        $this->createViewsBusinessDocument('AlbaranProveedor', 'supplier-delivery-notes');
        $this->createViewsBusinessDocument('FacturaProveedor', 'supplier-invoices');
        $this->createViewsBusinessDocument('PresupuestoCliente', 'customer-estimations');
        $this->createViewsBusinessDocument('PedidoCliente', 'customer-orders');
        $this->createViewsBusinessDocument('AlbaranCliente', 'customer-delivery-notes');
        $this->createViewsBusinessDocument('FacturaCliente', 'customer-invoices');
        $this->createViewsUsers();
    }

    /**
     * 
     * @param string $modelName
     * @param string $title
     */
    protected function createViewsBusinessDocument(string $modelName, string $title)
    {
        $viewName = 'List' . $modelName;
        $this->addListView($viewName, $modelName, $title, 'fas fa-copy');
        $this->views[$viewName]->addOrderBy(['fecha', 'hora'], 'date', 2);
        $this->views[$viewName]->addOrderBy(['total'], 'total');
    }

    /**
     * 
     * @param string $viewName
     */
    protected function createViewsNotes(string $viewName = 'ListNotaProyecto')
    {
        $this->addListView($viewName, 'NotaProyecto', 'notes', 'fas fa-sticky-note');
        $this->views[$viewName]->addOrderBy(['fecha'], 'date', 2);
        $this->views[$viewName]->addSearchFields(['descripcion']);
    }

    /**
     * 
     * @param string $viewName
     */
    protected function createViewsStock(string $viewName = 'ListStockProyecto')
    {
        $this->addListView($viewName, 'StockProyecto', 'stock', 'fas fa-dolly');
        $this->views[$viewName]->addSearchFields(['referencia']);
        $this->views[$viewName]->addOrderBy(['referencia'], 'reference');
        $this->views[$viewName]->addOrderBy(['cantidad'], 'quantity');
        $this->views[$viewName]->addOrderBy(['disponible'], 'available');
        $this->views[$viewName]->addOrderBy(['reservada'], 'reserved');
        $this->views[$viewName]->addOrderBy(['pterecibir'], 'pending-reception');

        /// filters
        $this->views[$viewName]->addFilterNumber('cantidad', 'quantity', 'cantidad');
        $this->views[$viewName]->addFilterNumber('reservada', 'reserved', 'reservada');
        $this->views[$viewName]->addFilterNumber('pterecibir', 'pending-reception', 'pterecibir');
        $this->views[$viewName]->addFilterNumber('disponible', 'available', 'disponible');

        /// disable column
        $this->views[$viewName]->disableColumn('project');

        /// disable buttons
        $this->setSettings($viewName, 'btnDelete', false);
        $this->setSettings($viewName, 'btnNew', false);
        $this->setSettings($viewName, 'checkBoxes', false);

        if ($this->user->admin) {
            $this->addButton($viewName, [
                'action' => 'rebuild-stock',
                'color' => 'warning',
                'confirm' => true,
                'icon' => 'fas fa-magic',
                'label' => 'rebuild-stock'
            ]);
        }
    }

    /**
     * 
     * @param string $viewName
     */
    protected function createViewsUsers(string $viewName = 'EditUserProyecto')
    {
        $this->addEditListView($viewName, 'UserProyecto', 'users', 'fas fa-users');

        /// disable column
        $this->views[$viewName]->disableColumn('project');
    }

    /**
     * 
     * @param string $viewName
     */
    protected function createViewsTasks(string $viewName = 'ListTareaProyecto')
    {
        $this->addListView($viewName, 'TareaProyecto', 'tasks', 'fas fa-project-diagram');
        $this->views[$viewName]->addOrderBy(['fecha'], 'date', 2);
        $this->views[$viewName]->addOrderBy(['fechainicio'], 'start-date');
        $this->views[$viewName]->addOrderBy(['fechafin'], 'end-date');
        $this->views[$viewName]->addSearchFields(['descripcion', 'nombre']);

        /// filters
        $this->views[$viewName]->addFilterPeriod('fecha', 'date', 'fecha');
        $status = $this->codeModel->all('tareas_fases', 'idfase', 'nombre');
        $this->views[$viewName]->addFilterSelect('idfase', 'phase', 'idfase', $status);

        /// disable columns
        $this->views[$viewName]->disableColumn('project');
        $this->views[$viewName]->disableColumn('company');
    }

    /**
     * 
     * @param EditView $view
     */
    protected function disableProjectColumns(&$view)
    {
        foreach ($view->getColumns() as $group) {
            foreach ($group->columns as $col) {
                if ($col->name !== 'status') {
                    $view->disableColumn($col->name, false, 'true');
                }
            }
        }
    }

    /**
     * 
     * @param string $action
     *
     * @return bool
     */
    protected function execPreviousAction($action)
    {
        switch ($action) {
            case 'rebuild-stock':
                $idproyecto = (int) $this->request->query->get('code');
                if (ProjectStockManager::rebuild($idproyecto)) {
                    $this->toolBox()->i18nLog()->notice('project-stock-rebuild-ok');
                    return true;
                }
                $this->toolBox()->i18nLog()->warning('project-stock-rebuild-error');
                return true;

            default:
                return parent::execPreviousAction($action);
        }
    }

    /**
     * 
     * @param string   $viewName
     * @param EditView $view
     */
    protected function loadData($viewName, $view)
    {
        $mainViewName = $this->getMainViewName();
        $idproyecto = $this->getViewModelValue($mainViewName, 'idproyecto');

        switch ($viewName) {
            case $mainViewName:
                parent::loadData($viewName, $view);
                if (false === $view->model->exists()) {
                    $view->model->idempresa = $this->user->idempresa;
                    $view->model->nick = $this->user->nick;
                } elseif (false === $view->model->userCanSee($this->user)) {
                    $this->setTemplate('Error/AccessDenied');
                } elseif (false === $view->model->editable) {
                    $this->disableProjectColumns($view);
                    $this->setSettings('EditUserProyecto', 'active', false);
                } elseif (false === $view->model->privado) {
                    $this->setSettings('EditUserProyecto', 'active', false);
                }
                break;

            case 'EditUserProyecto':
            case 'ListAlbaranCliente':
            case 'ListAlbaranProveedor':
            case 'ListFacturaCliente':
            case 'ListFacturaProveedor':
            case 'ListNotaProyecto':
            case 'ListPedidoCliente':
            case 'ListPedidoProveedor':
            case 'ListPresupuestoCliente':
            case 'ListPresupuestoProveedor':
            case 'ListStockProyecto':
            case 'ListTareaProyecto':
                $where = [new DataBaseWhere('idproyecto', $idproyecto)];
                $view->loadData('', $where);
                break;
        }
    }
}
