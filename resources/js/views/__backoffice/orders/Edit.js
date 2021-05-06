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
import { Meetup } from '../../../models';
import { LinearIndeterminate } from '../../../ui/Loaders';
import { Master as MasterLayout } from '../layouts';

import { MeetupForm } from './Forms';

function Edit(props) {
    const [loading, setLoading] = useState(false);
    const [formValues, setFormValues] = useState([]);
    const [meetup, setMeetup] = useState({});
    const [message, setMessage] = useState({});

    /**
     * Fetch the Mevel.
     *
     * @param {number} id
     *
     * @return {undefined}
     */
    const fetchMeetup = async id => {
        setLoading(true);

        try {
            const meetup = await Meetup.show(id);

            setMeetup(meetup);
            setLoading(false);
        } catch (error) {
            setLoading(false);
        }
    };


    /**
     * Handle form submit, this should send an API response
     * to create a meetup.
     *
     * @param {object} values
     *
     * @param {object} form
     *
     * @return {undefined}
     */
    const handleSubmit = async (values, { setSubmitting, setErrors }) => {
        
        setSubmitting(false);

        setLoading(true);

        try {
 

            const updatedMeetup = await Meetup.update(meetup.id, {
                ...values
            });


            setMessage({
                type: 'success',
                body: 'Meetup actualizada',
                closed: () => setMessage({}),
            });

            setLoading(false);
            
            setMeetup(updatedMeetup);
            
        } catch (error) {
            if (!error.response) {
                throw new Error('Unknown error');
            }

            const { errors } = error.response.data;

            setErrors(errors);

            setLoading(false);
        }
    };

    useEffect(() => {
        if (Object.keys(meetup).length > 0) {
            return;
        }

        const { params } = props.match;
        const { location } = props;

        const queryParams = UrlUtils.queryParams(location.search);

        fetchMeetup(params.id);
    });

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

    const renderForm = () => {
        if (loading) {
            return renderLoading;
        }

        const defaultProfileValues = {
            name: meetup.name === null ? '' : meetup.name,
            description: meetup.description === null ? '' : meetup.description,
            date: meetup.date === null ? '' : meetup.date,
            temperature: meetup.temperature === null ? '' : meetup.temperature,
        };

        return (
            <MeetupForm
                {...other}
                values={
                    formValues[0] ? formValues[0] : defaultProfileValues
                }
                handleSubmit={handleSubmit}
            />
        );
    };

    return (
        <MasterLayout
            {...other}
            pageTitle="Editar Nivel"
            tabs={[]}
            message={message}
        >
            <div className={classes.pageContentWrapper}>
                {loading && <LinearIndeterminate />}

                <Paper>
                    <div className={classes.pageContent}>
                        <Typography
                            component="h1"
                            variant="h4"
                            align="center"
                            gutterBottom
                        >
                            Edición de la meetup
                        </Typography>

                        {renderForm()}
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

export default withStyles(styles)(Edit);
