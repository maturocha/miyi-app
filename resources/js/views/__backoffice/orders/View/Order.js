import React, { useState }  from 'react';
import PropTypes from 'prop-types';

import {
    Typography,
    withStyles,
    Link,
    Grid
} from '@material-ui/core';

import List from '@material-ui/core/List';
import ListItem from '@material-ui/core/ListItem';
import ListItemText from '@material-ui/core/ListItemText';
import ListItemAvatar from '@material-ui/core/ListItemAvatar';
import Avatar from '@material-ui/core/Avatar';

import Table from '@material-ui/core/Table';
import TableBody from '@material-ui/core/TableBody';
import TableCell from '@material-ui/core/TableCell';
import TableHead from '@material-ui/core/TableHead';
import TableRow from '@material-ui/core/TableRow';

import * as NavigationUtils from '../../../../helpers/Navigation';

import {
  Person as PersonIcon,
  ContactPhone as ContactPhoneIcon,
} from '@material-ui/icons';



const Order = props => {
    const { values, ...other } = props;

    const [message, setMessage] = useState({});



    return (
        <Grid container spacing={24}>
          <Grid item xs={12} sm={4}>
          <List >
          <ListItem>
            <ListItemAvatar>
              <Avatar>
                <PersonIcon />
              </Avatar>
            </ListItemAvatar>
            <ListItemText
              primary="Información"
              secondary={
                <React.Fragment>
                  <Typography
                    component="span"
                    variant="body2"
                    
                    color="textPrimary"
                  >
                  Nro pedido: {values.id}
                  </Typography>
                  <Typography
                    component="span"
                    variant="body2"
                    
                    color="textPrimary"
                  >
                  Fecha: {new Date(values.date).toLocaleDateString('es-AR', {
                    day : 'numeric',
                    month : 'numeric',
                    year : 'numeric'
                }).split(' ').join('/')}
                  </Typography>
                  <Typography
                    component="span"
                    variant="body2"
                    
                    color="textPrimary"
                  >
                    Notas: {values.notes}
                  </Typography>
                  <Typography
                    component="span"
                    variant="body2"
                    
                    color="textPrimary"
                  >
                  Vendido por: {values.name}
                  </Typography>
                 
                </React.Fragment>
              }
            />
          </ListItem>
          <ListItem>
            <ListItemAvatar>
              <Avatar>
                <ContactPhoneIcon />
              </Avatar>
            </ListItemAvatar>
            <ListItemText
              primary="Cliente"
              secondary={
                <React.Fragment>
                  <Link
                    component="span"
                    variant="body2"
                    color="textPrimary"
                    onClick={() => console.log(values)
                      // history.push(
                      //     NavigationUtils.route(
                      //         'backoffice.admin.meetups.show',
                      //         {
                      //             id: values.id_customer,
                      //         },
                      //     ),
                      // )
                  }
                  >
                    {values.customer}
                  </Link>

                  <Typography
                    component="span"
                    variant="body2"
                    
                    color="textPrimary"
                  >
                    Celular 2: {values.cel_phone_2}
                  </Typography>
                </React.Fragment>
              }
            />
          </ListItem>
          
        </List>
        </ Grid>
     
          <Grid item xs={12} sm={8}>

          
      <Table className='' aria-label="spanning table">
        <TableHead>
          <TableRow>
            <TableCell align="center" colSpan={4}>
              Artículos Vendidos
            </TableCell>
            <TableCell align="right">Precio</TableCell>
          </TableRow>
          <TableRow>
            <TableCell>Producto</TableCell>
            <TableCell align="right">Cant.</TableCell>
            <TableCell align="right">Precio</TableCell>
            <TableCell align="right">Desc</TableCell>
            <TableCell align="right">Subtotal</TableCell>
          </TableRow>
        </TableHead>
        <TableBody>
          {values.details.map((row) => (
            <TableRow key={row.id_product}>
              <TableCell>{row.name}</TableCell>
              <TableCell align="right">{row.quantity}</TableCell>
              <TableCell align="right">$ {row.price_unit}</TableCell>
              <TableCell align="right">{row.discount}</TableCell>
              <TableCell align="right">$ {row.price_final}</TableCell>
            </TableRow>
          ))}

          <TableRow>
            <TableCell rowSpan={3} />
            <TableCell colSpan={3}>Subtotal</TableCell>
            <TableCell align="right">$ {values.total_bruto}</TableCell>
          </TableRow>
          <TableRow>
            <TableCell colSpan={3}>Descuento</TableCell>
            <TableCell align="right">{values.discount} %</TableCell>
          </TableRow>
          <TableRow>
            <TableCell colSpan={3}>Envío</TableCell>
            <TableCell align="right">$ {values.delivery_cost}</TableCell>
          </TableRow>
          <TableRow>
            <TableCell/>
            <TableCell colSpan={3}>Total</TableCell>
            <TableCell align="right">$ {values.total}</TableCell>
          </TableRow>
        </TableBody>
      </Table>
    

        </ Grid>
      </ Grid>
   );
};

Order.propTypes = {
    values: PropTypes.object.isRequired,
};

const styles = theme => ({
    formControl: {
        minWidth: '100%',
    },

    required: {
        color: theme.palette.error.main,
    },
});

export default withStyles(styles)(Order);
