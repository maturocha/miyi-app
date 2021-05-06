import React, {useState} from 'react';
import PropTypes from 'prop-types';
import { Formik, Form } from 'formik';
import * as Yup from 'yup';

import { MuiPickersUtilsProvider, DatePicker } from 'material-ui-pickers';
import MomentUtils from '@date-io/moment';

import * as WeatherUtils from '../../../../helpers/Weather';

import {
    Button,
    FormControl,
    FormHelperText,
    Input,
    Grid,
    InputLabel,
    Typography,
    withStyles,
} from '@material-ui/core';


const MeetupForm = props => {
    const { classes, values, handleSubmit } = props;

    const [message, setMessage] = useState('');

    const getForecastTemperature = async (date, setFieldValue) => {
        let temp = await WeatherUtils.getForecastTemperature(date.format('YYYY-MM-DD'));

        if (temp) {
            setFieldValue('temperature', temp, false)
        } else {
            setFieldValue('temperature', '', false);
            setMessage('No hay pronóstico disponible para esa fecha')
        }
    }

    return (
        <Formik
            initialValues={values}
            validationSchema={Yup.object().shape({
                name: Yup.string().required(
                    'Obligatorio'
                )
            })}
            onSubmit={async (values, form) => {
                let mappedValues = {};
                let valuesArray = Object.values(values);

                // Format values specially the object ones (i.e Moment)
                Object.keys(values).forEach((filter, key) => {

                    mappedValues[filter] = valuesArray[key];
                });

                await handleSubmit(mappedValues, form);
            }}
            validateOnBlur={false}
        >
            {({
                values,
                errors,
                submitCount,
                isSubmitting,
                handleChange,
                setFieldValue,
            }) => (
                <Form>
                    <Typography variant="h6" gutterBottom>
                        Meetup
                    </Typography>

                    <Grid container spacing={24}>
                        <Grid item xs={12} sm={6}>
                            <FormControl
                                className={classes.formControl}
                                error={
                                    submitCount > 0 &&
                                    errors.hasOwnProperty('name')
                                }
                            >
                                <InputLabel htmlFor="name">
                                    Nombre{' '}
                                    <span className={classes.required}>*</span>
                                </InputLabel>

                                <Input
                                    id="name"
                                    name="name"
                                    value={values.name}
                                    onChange={handleChange}
                                    fullWidth
                                />

                                {submitCount > 0 &&
                                    errors.hasOwnProperty('name') && (
                                        <FormHelperText>
                                            {errors.firstname}
                                        </FormHelperText>
                                    )}
                            </FormControl>
                        </Grid>

                        <Grid item xs={12} sm={6}>
                            <FormControl
                                className={classes.formControl}
                                error={
                                    submitCount > 0 &&
                                    errors.hasOwnProperty('description')
                                }
                            >
                                <InputLabel htmlFor="description">
                                    Breve descripción
                                </InputLabel>

                                <Input
                                    id="description"
                                    name="description"
                                    value={values.description}
                                    onChange={handleChange}
                                    fullWidth
                                />

                                {submitCount > 0 &&
                                    errors.hasOwnProperty('description') && (
                                        <FormHelperText>
                                            {errors.description}
                                        </FormHelperText>
                                    )}
                            </FormControl>
                        </Grid>

                        <Grid item xs={12} sm={6}>
                            <FormControl
                                className={classes.formControl}
                                error={
                                    submitCount > 0 &&
                                    errors.hasOwnProperty('date')
                                }
                            >
                                <MuiPickersUtilsProvider utils={MomentUtils}>
                               
                                    <DatePicker
                                        id="date"
                                        name="date"
                                        label="Fecha de meetup *"
                                        placeholder="Seleccione una fecha"
                                        value={values.date}
                                        onChange={date => {
                                            setFieldValue('date', date)
                                            getForecastTemperature(date, setFieldValue)
                                        }
                                        }
                                        format="YYYY-MM-DD"
                                        
                                        keyboard
                                        clearable
                                        disablePast
                                    />
                                </MuiPickersUtilsProvider>

                                {submitCount > 0 &&
                                    errors.hasOwnProperty('date') && (
                                        <FormHelperText>
                                            {errors.date}
                                        </FormHelperText>
                                    )}
                            </FormControl>
                        </Grid>

                        <Grid item xs={12} sm={6}>
                            <FormControl
                                className={classes.formControl}
                               
                            >

                            <InputLabel htmlFor="temperature">
                                Temperatura aprox.
                            </InputLabel>

                            <Input
                                type="number"
                                id="temperature"
                                name="temperature"
                                value={values.temperature}
                                onChange={handleChange}
                                fullWidth
                                readOnly
                            />
                            {message &&
                                <FormHelperText>
                                    {message}
                                </FormHelperText>
                            }

                             
                            </FormControl>
                        </Grid>
                    
                    </Grid>

                    <div className={classes.sectionSpacer} />

                    <Grid container spacing={24} justify="flex-end">
                        <Grid item>
                            <Button
                                type="submit"
                                variant="contained"
                                color="primary"
                                disabled={
                                    (errors &&
                                        Object.keys(errors).length > 0 &&
                                        submitCount > 0) ||
                                    isSubmitting
                                }
                            >
                                Guardar
                            </Button>
                        </Grid>
                    </Grid>
                </Form>
            )}
        </Formik>
    );
};

MeetupForm.propTypes = {
    values: PropTypes.object.isRequired,
    handleSubmit: PropTypes.func.isRequired,
};

const styles = theme => ({
    formControl: {
        minWidth: '100%',
    },

    required: {
        color: theme.palette.error.main,
    },
});

export default withStyles(styles)(MeetupForm);
