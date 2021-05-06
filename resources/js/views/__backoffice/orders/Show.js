import React, { useState, useEffect } from 'react';

import {
    CircularProgress,
    Grid,
    Paper,
    Step,
    StepLabel,
    Stepper,
    Typography,
    withStyles,
} from '@material-ui/core';

import * as UrlUtils from '../../../helpers/URL';
import * as NavigationUtils from '../../../helpers/Navigation';
import { Order } from '../../../models';
import { LinearIndeterminate } from '../../../ui/Loaders';
import { Master as MasterLayout } from '../layouts';

import { OrderView } from './View';

function Show(props) {
    const [loading, setLoading] = useState(false);

    const [order, setOrder] = useState({});
    const [message, setMessage] = useState({});

    /**
     * Fetch the user.
     *
     * @param {number} id
     *
     * @return {undefined}
     */
    const fetchOrder = async id => {
        setLoading(true);

        try {
            const order = await Order.show(id);

            setOrder(order.data);
            setLoading(false);
        } catch (error) {
            setLoading(false);
        }
    };


    useEffect(() => {
        if (Object.keys(order).length > 0) {
            return;
        }

        const { params } = props.match;
        const { location } = props;

        const queryParams = UrlUtils.queryParams(location.search);

        fetchOrder(params.id);
    }, []);

    const { classes, ...other } = props;
    const { history } = props;


    const renderLoading = (
        <Grid
            container
            className={classes.loadingContainer}
            justify="center"
            alignItems="center"
        >
            <Grid item>
                <CircularProgress color="primary" />
            </Grid>
        </Grid>
    );

    const renderView = () => {
        if (loading) {
            return renderLoading;
        }

        const orderInfo = {

            id: order.id === null ? '' : order.id,
            date: order.date === null ? '' : order.date,
            delivery_cost: order.delivery_cost === null ? '' : order.delivery_cost,
            details: order.details === null ? '' : order.details,
            discount: order.discount === null ? '' : order.discount,
            id_customer: order.id_customer === null ? '' : order.id_customer,
            customer: order.customer === null ? '' : order.customer,
            name: order.name === null ? '' : order.name,
            notes: order.notes === null ? '' : order.notes,
            total: order.total === null ? '' : order.total,
            total_bruto: order.total_bruto === null ? '' : order.total_bruto,
            
        };
      
        return (
            <OrderView
                {...other}
                values={
                    orderInfo
                }
            />
        );

      
    };

    return (
        <MasterLayout
            {...other}
            pageTitle="Ver Pedido"
            tabs={[]}
            message={message}
        >
            <div className={classes.pageContentWrapper}>
                {loading && <LinearIndeterminate />}

                <Paper>
                    <div className={classes.pageContent}>
                        <Typography
                            component="h1"
                            variant="h5"
                            align="left"
                            gutterBottom
                        >
                           # {order.id}
                        </Typography>
                        {renderView()}
                    </div>
                </Paper>
            </div>
        </MasterLayout>
    );
}

const styles = theme => ({
    pageContentWrapper: {
        width: '100%',
        marginTop: theme.spacing.unit * 3,
        minHeight: '75vh',
        overflowX: 'auto',
    },

    pageContent: {
        padding: theme.spacing.unit * 3,
    },

    loadingContainer: {
        minHeight: 200,
    },
});

export default withStyles(styles)(Show);
