import React, { useState, useEffect, useContext } from 'react';

import {
    IconButton,
    Menu,
    MenuItem
} from '@material-ui/core';

import {
    ArrowDropDown as ArrowDropDownIcon
} from '@material-ui/icons';

import * as NavigationUtils from '../../../helpers/Navigation';
import * as UrlUtils from '../../../helpers/URL';
import { Table } from '../../../ui';
import { Master as MasterLayout } from '../layouts';
import { Order } from '../../../models';
import { AppContext } from '../../../AppContext';

function List(props) {
    const [loading, setLoading] = useState(false);
    const [pagination, setPagination] = useState({});
    const [sorting, setSorting] = useState({
        by: 'created_at',
        type: 'desc',
    });
    const [filters, setFilters] = useState({});
    const [message, setMessage] = useState({});
    const [alert, setAlert] = useState({});

    const [anchorEl, setAnchorEl] = React.useState(null);
  const open = Boolean(anchorEl);

  const handleClick = (event) => {
    setAnchorEl(event.currentTarget);
  };

  const handleClose = () => {
    setAnchorEl(null);
  };

    /**
     * Event listener that is triggered when a resource delete button is clicked.
     * This should prompt for confirmation.
     *
     * @param {string} resourceId
     *
     * @return {undefined}
     */
    const handleDeleteClick = resourceId => {
        setAlert({
            type: 'confirmation',
            title: Lang.get('resources.delete_confirmation_title', {
                name: 'User',
            }),
            body: Lang.get('resources.delete_confirmation_body', {
                name: 'User',
            }),
            confirmText: Lang.get('actions.continue'),
            confirmed: async () => await deleteUser(resourceId),
            cancelled: () => setAlert({}),
        });
    };

    /**
     * Event listener that is triggered when a filter is removed.
     * This should re-fetch the resource.
     *
     * @param {string} key
     *
     * @return {undefined}
     */
    const handleFilterRemove = async key => {
        const newFilters = { ...filters };
        delete newFilters[key];

        await fetchOrder({
            ...defaultQueryString(),
            filters: newFilters,
        });
    };

    /**
     * Event listener that is triggered when the filter form is submitted.
     * This should re-fetch the resource.
     *
     * @param {object} values
     * @param {object} form
     *
     * @return {undefined}
     */
    const handleFiltering = async (values, { setSubmitting }) => {
        setSubmitting(false);

        const newFilters = {
            ...filters,
            [`${values.filterBy}[${values.filterType}]`]: values.filterValue,
        };

        await fetchOrder({
            ...defaultQueryString(),
            filters: newFilters,
        });
    };

    /**
     * Event listener that is triggered when a sortable TableCell is clicked.
     * This should re-fetch the resource.
     *
     * @param {string} column
     *
     * @return {undefined}
     */
    const handleSorting = async (sortBy, sortType) => {
        await fetchOrder({
            ...defaultQueryString(),
            sortBy,
            sortType,
        });
    };

    /**
     * Event listener that is triggered when a Table Component's Page is changed.
     * This should re-fetch the resource.
     *
     * @param {number} page
     *
     * @return {undefined}
     */
    const handlePageChange = async page => {
        await fetchOrder({
            ...defaultQueryString(),
            page,
        });
    };

    /**
     * Event listener that is triggered when a Table Component's Per Page is changed.
     * This should re-fetch the resource.
     *
     * @param {number} perPage
     * @param {number} page
     *
     * @return {undefined}
     */
    const handlePerPageChange = async (perPage, page) => {
        await fetchOrder({
            ...defaultQueryString(),
            perPage,
            page,
        });
    };

    
      /**
     * Event listener that is triggered when a resource delete button is clicked.
     * This should prompt for confirmation.
     *
     * @param {string} resourceId
     *
     * @return {undefined}
     */
    const handleInscriptionClick = orderId => {
        setAlert({
            type: 'confirmation',
            title: 'Inscripción a order',
            body: 'Confirma que se quiere inscribir',
            confirmText: 'Inscribir',
            cancelText: 'Cancelar',
            confirmed: async () => await inscriptUser(orderId),
            cancelled: () => setAlert({}),
        });
    };

     /**
     * This should send an API request to delete a resource.
     *
     * @param {string} resourceId
     *
     * @return {undefined}
     */
    const inscriptUser = async orderId => {
        setLoading(true);

        try {

            let attr = {
                id_order : orderId
            }
            let inscription = await Inscription.store(attr);

            setLoading(false);
            setAlert({});
            setMessage({
                type: 'success',
                body: 'Inscripción exitosa',
                closed: () => setMessage({}),
                actionText: Lang.get('actions.undo'),
                action: () => restoreUser(resourceId),
            });
        } catch (error) {
            setLoading(false);
            setAlert({});
            setMessage({
                type: 'error',
                body: 'Error al inscribirte',
                closed: () => setMessage({}),
                actionText: 'Reintentar',
                action: () => inscriptUser(orderId),
            });
        }
    };

    /**
     * This should send an API request to delete a resource.
     *
     * @param {string} resourceId
     *
     * @return {undefined}
     */
    const deleteUser = async resourceId => {
        setLoading(true);

        try {
            const pagination = await User.delete(resourceId);

            setLoading(false);
            setPagination(pagination);
            setAlert({});
            setMessage({
                type: 'success',
                body: Lang.get('resources.deleted', {
                    name: 'User',
                }),
                closed: () => setMessage({}),
                actionText: Lang.get('actions.undo'),
                action: () => restoreUser(resourceId),
            });
        } catch (error) {
            setLoading(false);
            setAlert({});
            setMessage({
                type: 'error',
                body: Lang.get('resources.not_deleted', {
                    name: 'User',
                }),
                closed: () => setMessage({}),
                actionText: Lang.get('actions.retry'),
                action: () => deleteUser(resourceId),
            });
        }
    };

    /**
     * This should send an API request to fetch all resource.
     *
     * @param {object} params
     *
     * @return {undefined}
     */
    const fetchOrder = async (params = {}) => {
        setLoading(true);

        try {
            const {
                page,
                perPage,
                sortBy,
                sortType,
                filters: newFilters,
            } = params;

            const queryParams = {
                page,
                perPage,
                sortBy,
                sortType,
                ...newFilters,
            };

            const pagination = await Order.paginated(queryParams);

            setLoading(false);
            setSorting({
                by: sortBy ? sortBy : sorting.by,
                type: sortType ? sortType : sorting.type,
            });
            setFilters(newFilters ? newFilters : filters);
            setPagination(pagination);
            setMessage({});
        } catch (error) {
            setLoading(false);
        }
    };

    const fethInscriptions = async (params = {}) => {
        setLoading(true);

        try {
            const {
                page,
                perPage,
                sortBy,
                sortType,
                filters: newFilters,
            } = params;

            const queryParams = {
                page,
                perPage,
                sortBy,
                sortType,
                ...newFilters,
            };

            const pagination = await Inscription.paginated(queryParams);

            setLoading(false);
            setSorting({
                by: sortBy ? sortBy : sorting.by,
                type: sortType ? sortType : sorting.type,
            });
            setFilters(newFilters ? newFilters : filters);
            setPagination(pagination);
            setMessage({});
        } catch (error) {
            setLoading(false);
        }
    };

    /**
     * This will provide the default sorting, pagination & filters from state.
     *
     * @return {object}
     */
    const defaultQueryString = () => {
        const { sortBy, sortType } = sorting;
        const { current_page: page, per_page: perPage } = pagination;

        return {
            sortBy,
            sortType,
            perPage,
            page,
            filters,
        };
    };

    /**
     * This will update the URL query string via history API.
     *
     * @return {undefined}
     */
    const updateQueryString = () => {
        const { history, location } = props;
        const { current_page: page, per_page: perPage } = pagination;
        const { by: sortBy, type: sortType } = sorting;

        const queryString = UrlUtils.queryString({
            page,
            perPage,
            sortBy,
            sortType,
            ...filters,
        });

        history.push(`${location.pathname}${queryString}`);
    };

    /**
     * Fetch data on initialize.
     */
    useEffect(() => {
        if (pagination.hasOwnProperty('data')) {
            updateQueryString();

            return;
        }

        const { location } = props;
        const queryParams = location.search
            ? UrlUtils.queryParams(location.search)
            : {};

        const prevFilters = {};
        const queryParamValues = Object.values(queryParams);

        Object.keys(queryParams).forEach((param, key) => {
            if (param.search(/\[*]/) > -1 && param.indexOf('_') < 0) {
                prevFilters[param] = queryParamValues[key];
            }
        });

        fetchOrder({
            ...queryParams,
            filters: prevFilters,
        });
    }, [pagination.data]);

    const { user: authUser } = useContext(AppContext);
    const { ...childProps } = props;
    const { history } = props;

    const {
        data: rawData,
        total,
        per_page: perPage,
        current_page: page,
    } = pagination;

    const tabs = [
        {
            name: 'Listado',
            active: true,
        },
    ];

    const columns = [
        { name: '# Pedido', property: 'id', sort: false },
        { name: 'Fecha', property: 'date', sort: false },
        { name: 'Cliente', property: 'customer', sort: false },
        { name: 'Total', property: 'total', sort: false },
        {
            name: 'Acciones',
            property: 'actions',
            filter: false,
            sort: false,
        },
    ];

    const data =
        rawData &&
        rawData.map(order => {
            return {
                id: order.id,
                date: new Date(order.date).toLocaleDateString('es-AR', {
                    day : 'numeric',
                    month : 'short'
                }).split(' ').join(' '),
                customer: order.customer,
                total: `$ ${order.total}`,
                actions: (
                    <div style={{ width: 120, flex: 'no-wrap' }}>
                        <IconButton
                         
                            onClick={handleClick}
                        >
                            <ArrowDropDownIcon />
                        </IconButton>
                        <Menu
                            id="long-menu"
                            anchorEl={anchorEl}
                            keepMounted
                            open={open}
                            onClose={handleClose}
                            PaperProps={{
                            style: {
                                maxHeight: 48 * 4.5,
                                width: '20ch',
                            },
                            }}
                        >
                            <MenuItem
                                 onClick={() =>
                                    history.push(
                                        NavigationUtils.route(
                                            'backoffice.general.orders.view',
                                            {
                                                id: order.id,
                                            },
                                        ),
                                    )
                                }
                            >Ver</MenuItem>
                            <MenuItem
                                 onClick={() =>
                                    history.push(
                                        NavigationUtils.route(
                                            'backoffice.general.orders.edit',
                                            {
                                                id: order.id,
                                            },
                                        ),
                                    )
                                }
                            >Editar</MenuItem>
                            <MenuItem>Imprimir</MenuItem>
                            <MenuItem
                             onClick={() =>
                                history.push(
                                    NavigationUtils.route(
                                        'backoffice.general.orders.delete',
                                        {
                                            id: order.id,
                                        },
                                    ),
                                )
                            }
                            >Eliminar</MenuItem>
                        </Menu>
                     
                        
                    </div>
                ),
            };
        });

    return (
        <MasterLayout
            {...childProps}
            loading={loading}
            pageTitle='Pedidos'
            tabs={tabs}
            loading={loading}
            message={message}
            alert={alert}
        >
            {!loading && data && (
                <Table
                    title='Pedidos'
                    data={data}
                    total={total}
                    columns={columns}
                    filters={filters}
                    sortBy={sorting.by}
                    sortType={sorting.type}
                    headerCellClicked={cellName =>
                        handleSorting(
                            cellName,
                            sorting.type === 'asc' ? 'desc' : 'asc',
                        )
                    }
                    page={parseInt(page)}
                    perPage={parseInt(perPage)}
                    onChangePage={handlePageChange}
                    onChangePerPage={handlePerPageChange}
                    onFilter={handleFiltering}
                    onFilterRemove={handleFilterRemove}
                />
            )}
        </MasterLayout>
    );
}

export default List;
