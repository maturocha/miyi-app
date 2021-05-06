import React, {useState, useEffect } from 'react';
import PropTypes from 'prop-types';

import Button from '@material-ui/core/Button';
import AppBar from '@material-ui/core/AppBar';
import Dialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogContentText from '@material-ui/core/DialogContentText';
import DialogTitle from '@material-ui/core/DialogTitle';
import Grid from '@material-ui/core/Grid';
import Paper from '@material-ui/core/Paper';

import Toolbar from '@material-ui/core/Toolbar';
import IconButton from '@material-ui/core/IconButton';
import Typography from '@material-ui/core/Typography';
import CloseIcon from '@material-ui/icons/Close';

import { Input, InputLabel } from '@material-ui/core';
import AddShoppingCartIcon from '@material-ui/icons/AddShoppingCart';

import { Product } from '../../../../models';

import AsyncSelect from 'react-select/async';

import {
    withStyles,
} from '@material-ui/core';


const Items = props => {
    const { classes, value, typePrice, addItemsToOrder, total } = props;

    const [message, setMessage] = useState('');
    const [open, setOpen] = useState(false);
    const [inputValue, setValue] = useState('');
    const [selectedValue, setSelectedValue] = useState(null);
    const [product, setProduct] = useState(null);
    const [quantity, updateQuantity] = useState("");
    const [discount, updateDiscount] = useState(0);
    const [subtotal, updateSubtotal] = useState(0);
    const [error, setError] = useState({})

    useEffect(() => {
      const timeoutId = setTimeout(function () {

        if (!quantity) {
          updateQuantity("")
          calculateSubtotal()
          return null
        }
    
        let newValue = quantity;
        let actual_stock = (product.own_product == 1) ? parseFloat(product.stock) : 100000000;
        let rest = quantity % product.interval_quantity

        if ((quantity <= actual_stock) && (rest != 0) ) {
          newValue = quantity - rest
        } else if (quantity > actual_stock) {
          newValue = actual_stock
        }
    
        if (newValue != quantity) {
          updateQuantity(newValue)
        }

        calculateSubtotal()
          

      }, 750)
      return () => clearTimeout(timeoutId);
    }, [quantity]);

    useEffect(() => {
      
      if (discount > 0)
        calculateSubtotal()

    }, [discount]);

    const calculateSubtotal = () => {
      
      if (!product)
        return null

      let subtotal_aux = 0
      let price_aux = (typePrice == 'm') ? product.price_unit : product.price_min

      if (parseFloat(quantity))
        subtotal_aux = quantity * price_aux
      
      if (parseFloat(discount))
        subtotal_aux = subtotal_aux - ((discount * quantity * price_aux) / 100)

      updateSubtotal(subtotal_aux.toFixed(2))
      
    }
    

    const  handleClickOpen = () => {
        setOpen(true);
    };
    
    const  handleClose = () => {
      setOpen(false)
    };

    // handle input change event
    const handleInputChange = value => {
        setValue(value);
    };
 
  // handle selection
  const handleChangeSelect = async (selectedOption) => {

    setSelectedValue(selectedOption);

    const product = await Product.show(selectedOption.value);

    setProduct(product)

  }

  const addItemsClick = () => {

    if (subtotal <= 0) {
      setError({
        ...error,
        subtotal: true
      })
      return null
    }

    if ( quantity <= 0 || quantity == '' ) {
      setError({
        ...error,
        quantity: true
      })
      return null
    }
      

    setProduct(null)
    setSelectedValue(null)
    updateQuantity("")
    updateDiscount("")
    updateSubtotal(0)

    let item = {
      name: product.name,
      quantity: quantity,
      discount: discount,
      price_unit: (typePrice == 'm') ? product.price_unit : product.price_min,
      price_final: subtotal,
    }

    addItemsToOrder(item)

  }
  // load options using API call
  const loadOptions = async (query) => {
    if (query.length < 3 )
      return null;
    
    try {

      const products = await Product.paginated({search: query});
      const suggestions = products.data.map(customer => ({
          value: customer.id,
          label: customer.text,
        }));

      return suggestions;
    
    } catch (error) {

      return null

    }

  };


    return (
       <>
        <Button variant="outlined" color="primary" onClick={handleClickOpen}>
          + Agregar productos
        </Button>
        <Dialog
            fullScreen
            onClose={handleClickOpen}
            open={open}
            //onClose={handleClose}
            aria-labelledby="form-dialog-title"
        >
          <AppBar className={classes.appBar}>
            <Toolbar>
              <IconButton color="inherit" onClick={handleClose} aria-label="Close">
                <CloseIcon />
              </IconButton>
              <Typography variant="h6" color="inherit" className={classes.flex}>
                Agregar Productos
              </Typography>
              <Button color="inherit" onClick={handleClose}>
                Terminar
              </Button>
            </Toolbar>
          </AppBar>
          <DialogTitle id="form-dialog-title">Productos</DialogTitle>
          <DialogContent>
            <DialogContentText>
              
            </DialogContentText>
            <AsyncSelect
                autoFocus
                cacheOptions
                defaultOptions
                value={selectedValue}
                loadOptions={loadOptions}
                onInputChange={handleInputChange}
                onChange={handleChangeSelect}
                placeholder='Busque un producto'
            />
            {product &&
              <Grid container className={classes.grid} spacing={24}>
                <Grid item xs={6} sm={3}>
                  <Paper className={classes.info}>Stock: {(product.own_product == 1) ? product.stock : '∞'} </Paper>
                </Grid>
                <Grid item xs={6} sm={3}>
                  <Paper className={classes.info}>Se vende por: {product.interval_quantity}</Paper>
                </Grid>
                <Grid item xs={6} sm={3}>
                  <Paper className={classes.info}>Bulto: {product.bulto}</Paper>
                </Grid>
                <Grid item xs={6} sm={3}>
                  <Paper className={classes.info}>Precio: ${(typePrice == 'm') ? product.price_unit : product.price_min}</Paper>
                </Grid>
                <Grid item xs={12} sm={5}>
                  <InputLabel htmlFor="quantity">
                                    Cantidad{' '}
                                    <span></span>
                  </InputLabel>

                  <Input
                      error={error.quantity ? true : false}
                      name="quantity"
                      type="number"
                      value={quantity}
                      onChange={(event) => updateQuantity(event.target.value)}
                      fullWidth
                  />
                </Grid>
                <Grid item xs={12} sm={5}>
                  <InputLabel htmlFor="discount">
                      Descuento %{' '}
                      <span></span>
                  </InputLabel>

                  <Input
                      name="discount"
                      type="number"
                      min={1}
                      step={1}
                      max={100}
                      value={discount}
                      onChange={(event) => updateDiscount(event.target.value)}
                      fullWidth
                  />
                </Grid>
                <Grid item xs={12} sm={5}>
                  <h3>Subtotal: $ {subtotal} </h3>
                </Grid>
                <Grid item xs={12} sm={2}>
                 
                  <Button
                    fullWidth={true}
                    variant="contained"
                    color="primary"
                    size="large"
                    onClick={addItemsClick}
                  >
                    <AddShoppingCartIcon />
                      Agregar
                  </Button>
                </Grid>
              </Grid>
            }
          </DialogContent>
          <DialogActions className={classes.dialogFooter}>
          <h3>Total pedido: $ {total} </h3>
          </DialogActions>
        </Dialog>
      
      </>
    );
};

Items.propTypes = {
    // value: PropTypes.object.isRequired,
    // handleChange: PropTypes.func.isRequired,
};

const styles = theme => ({
  appBar: {
    position: 'relative',
  },
  dialogFooter: {
    margin: 0,
    justifyContent: 'center',
    backgroundColor: theme.palette.text.secondary,
    color: 'white',
    padding: '16px 4px',
  },
  flex: {
    flex: 1,
  },
    formControl: {
        minWidth: '100%',
    },
    grid: {
      margin: '4px -12px',
      width: 'unset'
    },
    info: {
      padding: '4px',
      textAlign: 'center',
      color: theme.palette.text.secondary,
      whiteSpace: 'nowrap',
      fontWeight: 'bold'
    },

    required: {
        color: theme.palette.error.main,
    },
});

export default withStyles(styles)(Items);
