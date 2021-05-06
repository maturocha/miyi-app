import React, { useState, useEffect, useContext } from 'react';

import {
    IconButton,
    Tooltip,
    Typography,
} from '@material-ui/core';

import {
    Delete as DeleteIcon,
    Edit as EditIcon,
    Image as ImageIcon,
    Update as UpdateIcon,
} from '@material-ui/icons';

import * as NavigationUtils from '../../../helpers/Navigation';
import * as UrlUtils from '../../../helpers/URL';
import { Table } from '../../../ui';
import { Master as MasterLayout } from '../layouts';
import { Meetup, Inscription } from '../../../models';
import { AppContext } from '../../../AppContext';

import * as WeatherUtils from '../../../helpers/Weather';

function List(props) {
    const [loading, setLoading] = useState(false);
    const [pagination, setPagination] = useState({});
    const [inscriptions, setInscriptions] = useState({});
    const [sorting, setSorting] = useState({
        by: 'name',
        type: 'asc',
    });
    const [filters, setFilters] = useState({});
    const [message, setMessage] = useState({});
    const [alert, setAlert] = useState({});

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
            title: 'Estas borrando una Meetup',
            body: 'Continuar?',
            confirmText: 'Sí',
            cancelText: 'No',
            confirmed: async () => await deleteMeetup(resourceId),
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

        await fethMeetup({
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

        await fethMeetup({
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
        await fethMeetup({
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
        await fethMeetup({
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
        await fethMeetup({
            ...defaultQueryString(),
            perPage,
            page,
        });
    };


    /**
     * This should send an API request to delete a resource.
     *
     * @param {string} resourceId
     *
     * @return {undefined}
     */
    const deleteMeetup = async resourceId => {
        setLoading(true);

        try {
            const pagination = await Meetup.delete(resourceId);

            setLoading(false);
            setPagination(pagination);
            setAlert({});
            setMessage({
                type: 'success',
                body: 'Meetup borrado con éxito',
                closed: () => setMessage({})
            });
        } catch (error) {
            setLoading(false);
            setAlert({});
            setMessage({
                type: 'error',
                body: 'No se pudo borrar su Meetup',
                closed: () => setMessage({}),
                actionText: 'Reintentar',
                action: () => deleteMeetup(resourceId),
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
    const fethMeetup = async (params = {}) => {
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

            //Get Meetups
            const pagination = await Meetup.paginated(queryParams);

            //Get Inscriptions
            const inscriptions_list = await Inscription.paginated();
            let inscriptions_dictionary = inscriptions_list.reduce((a,x) => ({...a, [x.id]: x.quantity}), {})
            setInscriptions(inscriptions_dictionary);

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
     * This will update the URL query string via history API.
     *
     * @return {undefined}
     */
    const getForecastTemperature = async (id_meetup, date, tempOld) => {

        let temp = await WeatherUtils.getForecastTemperature(date);

        if (temp) {

            if (temp != tempOld) {
                const updatedMeetup = await Meetup.update(id_meetup, {
                    temperature: temp
                });

                setMessage({
                    type: 'success',
                    body: 'Temperatura actualizada',
                    closed: () => setMessage({})
                });

                fethMeetup();

            } else {

                setMessage({
                    type: 'info',
                    body: 'Su temperatura ya estaba actualizada',
                    closed: () => setMessage({})
                });

                
            }

            
        } else {
            setMessage({
                type: 'error',
                body: 'No hay pronóstico disponible para esa fecha',
                closed: () => setMessage({})
            });
        }
    }


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

        fethMeetup({
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

    const primaryAction = {
        text: 'Crear Meetup',
        clicked: () =>
            history.push(
                NavigationUtils.route('backoffice.admin.meetups.create'),
            ),
    };

    const tabs = [
        {
            name: 'Listado',
            active: true,
        },
    ];

    const columns = [
        { name: 'Nombre', property: 'name', sort: true },
        { name: 'Descripción', property: 'description', sort: false },
        { name: 'Fecha', property: 'date', sort: true },
        { name: 'Temperatura (°)', property: 'temperature', sort: true },
        { name: 'Inscriptos', property: 'inscriptions', sort: true },
        { name: 'Cajas de Birras a comprar', property: 'beer_boxes', sort: true },
        {
            name: 'Acciones',
            property: 'actions',
            filter: false,
            sort: false,
        },
    ];

    const data =
        rawData &&
        rawData.map(meetup => {
            return {
                type: meetup.name,
                description: meetup.description,
                date: meetup.date,
                temperature: meetup.temperature,
                inscriptions: inscriptions[meetup.id],
                beers: meetup.beer_boxes,
                actions: (
                    <div style={{ width: 120, flex: 'no-wrap' }}>
                         <Tooltip
                            title='Actualizar temperatura'
                        >
                            <IconButton
                                onClick={() =>
                                    getForecastTemperature(meetup.id, meetup.date, meetup.temperature)
                                }
                            >
                                <UpdateIcon />
                            </IconButton>
                        </Tooltip>
                        <Tooltip
                            title='Editar'
                        >
                            <IconButton
                                onClick={() =>
                                    history.push(
                                        NavigationUtils.route(
                                            'backoffice.admin.meetups.edit',
                                            {
                                                id: meetup.id,
                                            },
                                        ),
                                    )
                                }
                            >
                                <EditIcon />
                            </IconButton>
                        </Tooltip>

                        {authUser.id == meetup.id_owner && (
                            <Tooltip
                                title="Eliminar"
                            >
                                <IconButton
                                    color="secondary"
                                    onClick={() => handleDeleteClick(meetup.id)}
                                >
                                    <DeleteIcon />
                                </IconButton>
                            </Tooltip>
                        )}
                    </div>
                ),
            };
        });

    return (
        <MasterLayout
            {...childProps}
            loading={loading}
            pageTitle='Meetups'
            primaryAction={primaryAction}
            tabs={tabs}
            loading={loading}
            message={message}
            alert={alert}
        >
            {!loading && data && (
                <Table
                    title='Meetups'
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
