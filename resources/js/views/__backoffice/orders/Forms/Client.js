import React, {useState, useEffect } from 'react';
import PropTypes from 'prop-types';


import Grid from '@material-ui/core/Grid';
import Paper from '@material-ui/core/Paper';

import {
  
    Typography,
  
} from '@material-ui/core';


import {
    LocationOn as LocationOnIcon,
    Phone as PhoneIcon
} from '@material-ui/icons';

import { Customer } from '../../../../models';

import Select from 'react-select';

import {
    withStyles,
} from '@material-ui/core';


const Client = props => {
    const { classes, customer, setCustomer } = props;

    const [message, setMessage] = useState('');
    const [customerList, setCustomerList] = useState({});
    const [optionSelect, setSelectedOption] = useState(null);

    const customStyles = {
       
        container: () => ({
          // none of react-select's styles are passed to <Control />
          width: '100%',
        }),
       
      }


    const fetchCustomers = async () => {

        

        try {
            //GET customers
            const customers = await Customer.paginated({perPage: 1000})
            const suggestions = customers.data.map(customer => ({
                value: customer.id,
                label: customer.name,
              }))

            setCustomerList(suggestions);
          
        } catch (error) {
            
        }
    };

    const handleChange = async (selectedOption) => {

        //setSelectedOption(selectedOption)
        const customer = await Customer.show(selectedOption.value);
        setCustomer(customer)

    }



    useEffect(() => {
        
        fetchCustomers()

    }, []);


    return (
       <>
        <Select
            inputProps={{autoComplete: 'off', autoCorrect: 'off', spellCheck: 'off' }}
            autoFocus
            styles={customStyles}
            value={customer ? {
                value: customer.id,
                label: customer.name,
              } : null}
            onChange={handleChange}
            options={customerList}
            placeholder='Busque un cliente'
            />
        {customer &&
         <Grid container className={classes.grid} spacing={24}>
            <Grid item xs={6}>
                <Paper className={classes.info}>
                        <LocationOnIcon /> {customer.address}
                </Paper>
            </Grid>
            <Grid item xs={6}>
                <Paper className={classes.info}>
                        <PhoneIcon /> {customer.cellphone}
                </Paper>
            </Grid>
        </Grid>
        }
      </>
    );
};

Client.propTypes = {
    setCustomer: PropTypes.func.isRequired,
};

const styles = theme => ({
    formControl: {
        minWidth: '100%',
    },
    grid: {
        margin: '4px -12px',
        width: 'unset'
      },
      info: {
        padding: '8px',
        textAlign: 'center',
        
        
        fontWeight: 'bold'
      },

    required: {
        color: theme.palette.error.main,
    },
});

export default withStyles(styles)(Client);
