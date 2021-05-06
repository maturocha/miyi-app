import React, { useState } from 'react';

import {
    Paper,
    Typography,
    withStyles,
} from '@material-ui/core';

import * as NavigationUtils from '../../../helpers/Navigation';
import { Meetup } from '../../../models';
import { LinearIndeterminate } from '../../../ui/Loaders';
import { Master as MasterLayout } from '../layouts';

import { MeetupForm } from './Forms';

function Create(props) {
    const [loading, setLoading] = useState(false);
    const [formValues, setFormValues] = useState([]);
    const [message, setMessage] = useState({});

    const { history } = props;


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


            const meetup = await Meetup.store(values);
            
            setMessage({
                type: 'success',
                body: 'Meetup "'+meetup.name +'" creada con éxito',
                closed: () => setMessage({}),
            });

            setLoading(false);
            //setFormValues(newFormValues);
            //setMeetup(meetup);

            history.push(
                NavigationUtils.route(
                    'backoffice.admin.meetups.index',
                ),
            )

        } catch (error) {
            console.log(error)
            if (!error.response) {
                throw new Error('Unknown error');
            }

            const { errors } = error.response.data;

            setErrors(errors);

            setLoading(false);
        }
    };

    const { classes, ...other } = props;

    const renderForm = () => {
        const defaultProfileValues = {
            name: '',
            description: '',
            date: null,
            temperature: ''
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
            pageTitle="Crear nivel"
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
                            Nueva Meetup
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
});

export default withStyles(styles)(Create);
