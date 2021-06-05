import React, { useState, useEffect, useRef } from 'react';

import {
    Grid,
    Select,
    NativeSelect,
    Input,
    TextField,
    InputLabel,
    ExpansionPanel,
    ExpansionPanelDetails,
    ExpansionPanelSummary,
    Typography,
    withStyles,
} from '@material-ui/core';

import Table from '@material-ui/core/Table';
import TableBody from '@material-ui/core/TableBody';
import TableCell from '@material-ui/core/TableCell';
import TableHead from '@material-ui/core/TableHead';
import TableRow from '@material-ui/core/TableRow';

import * as NavigationUtils from '../../../helpers/Navigation';
import { Order, OrderDetails } from '../../../models';

import { LinearIndeterminate } from '../../../ui/Loaders';
import { Master as MasterLayout } from '../layouts';

import Button from '@material-ui/core/Button';
import IconButton from '@material-ui/core/IconButton';
import DeleteIcon from '@material-ui/icons/Delete';


import {
    ExpandMore as ExpandMoreIcon,
    Person as PersonIcon,
    ShoppingCart as ShoppingCartIcon,
    AddShoppingCart as AddShoppingCartIcon,
    InsertComment as InsertCommentIcon,
    AttachMoney as AttachMoneyIcon
} from '@material-ui/icons';

import { ClientForm, ItemsForm } from './Forms';

function Create(props) {
    const { history } = props;
    const [loading, setLoading] = useState(false);
    const [formValues, setFormValues] = useState([]);
    const [message, setMessage] = useState({});
    const [expanded, setExpanded] = useState('customer');
    const [orderID, setOrderID] = useState(16431);
    const [customer, setCustomer] = useState(null);
    const [items, setItems] = useState([]);
    const [order, setOrder] = useState({
        total_bruto: 0,
        total: 0,
        delivery_cost: "",
        discount: "",
        payment_method: ""
    });

    const createOrder = async () => {
        setLoading(true);
        try {
            const orderItem = await Order.store({});
            setOrderID(orderItem.id)
            setLoading(false);
        } catch (error) {
            setLoading(false);
        }
    };

    //First render
    useEffect(() => {
        
        //createOrder();
        

    }, []);


    useEffect(() => {
        if (customer) {
            if (items.length == 0) {
                calculateTotal(false)
            } else {
                calculateTotal()
            }
        }
            

    }, [order.total_bruto]);

    useEffect(() => {
        if (customer)
            calculateTotal(false)

    }, [order.discount]);

    useEffect(() => {

        if (( order.payment_method == 'ef' ) && (customer.type == 'p'))
            setOrder(
                prevState => ({ 
                    ...prevState,
                    discount: 10
                })
            )

    }, [order.payment_method]);

    const calculateTotal = (delivery_calculate = true) => {

        let total_aux = order.total_bruto
        let delivery_aux = order.delivery_cost

        if (parseFloat(order.discount))
            total_aux = total_aux - ((order.discount * total_aux) / 100)

        if (delivery_calculate) {
            delivery_aux = ((total_aux < 1500) && customer.type == 'p') ? 150 : 0;
            total_aux = total_aux + delivery_aux
        }

        if (total_aux != order.total)
            setOrder(
                prevState => ({ 
                    ...prevState,
                    total: Number(total_aux.toFixed(2)),
                    delivery_cost: delivery_aux
                })
            )

    }
  
    
    const handleChangePanel = (panel) => (event, isExpanded) => {
        if (customer) {
            setExpanded(isExpanded ? panel : false);
        } else {
              
            setMessage({
                type: 'error',
                body: 'Debe elegir un cliente primero',
                closed: () => setMessage({}),
            });
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
    const handleChangePanelCustomer = selectedOption => {
        setCustomer(selectedOption);
        setExpanded('items');
    }


    const handleInputChangeDiscount = (target) => {
        setOrder(
            prevState => ({ 
                ...prevState,
                [target.name]: Number(target.value)
            })
        )

        calculateTotal(false)
    }

    const handleInputChangeOrder = (target, type) => {
        setOrder(
            prevState => ({ 
                ...prevState,
                [target.name]: (type == 'number') ? Number(target.value) : target.value
            })
        )
    }

    const { classes, ...other } = props;

    const renderClientSearch = () => {
        
        return (
            <ClientForm
                {...other}
                customer={customer}
                setCustomer={handleChangePanelCustomer}
            />
        );
    };


    const deleteItemsFromOrder = async (index) => {

        let id = items[index].id
        const details = await OrderDetails.delete(id);

        setOrder(
            prevState => ({ 
                ...prevState,
                total_bruto: Number((parseFloat(prevState.total_bruto) - parseFloat(items[index].price_final)).toFixed(2)) 
            })
        );
        setMessage({
            type: 'success',
            body: '"'+items[index].name +'" eliminado',
            closed: () => setMessage({}),
        });
        setItems(
            (prevState) => {
                prevState.splice(index, 1);
                return([ ...prevState ]);
              }
        );
        
    }




    const addItemsToOrder = async (item) => {

        //Add item to order backend
        let values = {... item}
        values.id_order = orderID;

        let details = await OrderDetails.store(values);
        let new_item = {...item, ...details }
        
        setItems(
            (prevState) => {
              prevState.push(new_item);
              return([ ...prevState ]);
            }
          );

          setOrder(
            prevState => ({ 
                ...prevState,
                total_bruto: Number((parseFloat(prevState.total_bruto) + parseFloat(details.price_final)).toFixed(2)) 
            })
          );

          setMessage({
            type: 'success',
            body: '"'+item.name +'" agregado',
            closed: () => setMessage({}),
        });


    }

    const saveOrder = async () => {

        setLoading(true);
        try {
            let values = {...order}
            values.id_customer = customer.id;
    
            const updatedOrder = await Order.update(orderID, {
                ...values
            });
            
            setMessage({
                type: 'success',
                body: 'Orden "'+updatedOrder.id +'" creada con éxito',
                closed: () => setMessage({}),
            });

            setLoading(false);

            history.push(
                NavigationUtils.route(
                    'backoffice.general.orders.index',
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



    }



    const renderItemsSearch = () => {
        
        return (
            <ItemsForm
                typePrice={customer ? customer.type : 'm'}
                total={order.total}
                addItemsToOrder={addItemsToOrder}
                {...other}
                
                
            />
        );
    };

    return (
        <MasterLayout
            {...other}
            pageTitle={`Nuevo Pedido # ${orderID}`}
            tabs={[]}
            message={message}
            showBreadcrumbs={false}
        >
            <div className={classes.pageContentWrapper}>
                {loading && <LinearIndeterminate />}

                <ExpansionPanel expanded={expanded === 'customer'} onChange={handleChangePanel('customer')}>
                    <ExpansionPanelSummary expandIcon={<ExpandMoreIcon />}>
                        <PersonIcon />
                        <Typography className={classes.heading}>
                            {customer ? customer.name : 'Cliente'}
                        </Typography>
                    </ExpansionPanelSummary>
                    <ExpansionPanelDetails className={classes.panelDetails}>
                        {renderClientSearch()}   
                    </ExpansionPanelDetails>
                </ExpansionPanel>

                <ExpansionPanel expanded={expanded === 'items'} onChange={handleChangePanel('items')}>
                    <ExpansionPanelSummary expandIcon={<ExpandMoreIcon />}>
                        <ShoppingCartIcon />
                        <Typography className={classes.heading}>
                            Productos (${order.total_bruto ? order.total_bruto : '0.00'})
                        </Typography>
                    </ExpansionPanelSummary>
                    <ExpansionPanelDetails className={classes.panelDetails}>
                        <Table className={classes.table}>
                            <TableBody>
                            {items.map((items, index) => (
                                <TableRow key={items.quantity}>
                                    <TableCell scope="row"  className={classes.cell}>
                                        {items.name} <br></br>
                                        {items.quantity} x {items.price_unit} <br></br>
                                        {items.discount} %
                                    </TableCell>
                                    <TableCell  className={classes.cell_subtotal}>
                                        <IconButton 
                                            className={classes.button} aria-label="Delete"
                                            onClick={() => deleteItemsFromOrder(index)}
                                        >
                                            <DeleteIcon/>
                                        </IconButton>
                                        <b>$ {items.price_final}</b></TableCell>
                                </TableRow>
                            ))}
                            </TableBody>
                        </Table> 
                        {renderItemsSearch()}  
                       
                    </ExpansionPanelDetails>
                </ExpansionPanel>

                <ExpansionPanel expanded={expanded === 'extras'} onChange={handleChangePanel('extras')}>
                    <ExpansionPanelSummary expandIcon={<ExpandMoreIcon />}>
                        <AddShoppingCartIcon />
                        <Typography className={classes.heading}>
                            Extras
                        </Typography>
                    </ExpansionPanelSummary>
                    <ExpansionPanelDetails className={classes.panelDetailsRow}>
                    <Grid item xs={12} sm={4}>
                        <InputLabel shrink htmlFor="payment_method">
                            Método de pago
                        </InputLabel>
                        <NativeSelect
                            onChange={(event) => handleInputChangeOrder(event.target, 'text')}
                            className={classes.selectNative}
                            value={order.payment_method}
                            input={<Input name="payment_method"/>}
                        >
                            <option value="">No definido</option>
                            <option value="ef">Efectivo</option>
                            <option value="trans">Transferencia</option>
                            <option value="mp">Mercado Pago</option>
                        </NativeSelect>
                    </Grid>
                        <Grid item xs={12} sm={4}>
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
                                value={order.discount}
                                onChange={(event) => handleInputChangeOrder(event.target, 'number') }
                                fullWidth
                            />
                        </Grid>   
                        <Grid item xs={12} sm={4}>
                            <InputLabel htmlFor="delivery_cost">
                                Costo de envío
                                <span></span>
                            </InputLabel>

                            <Input
                                name="delivery_cost"
                                type="number"
                                value={order.delivery_cost}
                                onChange={(event) => handleInputChangeDiscount(event.target) }
                                fullWidth
                            />
                        </Grid>   
                    </ExpansionPanelDetails>
                </ExpansionPanel>

                <ExpansionPanel expanded={expanded === 'comments'} onChange={handleChangePanel('comments')}>
                    <ExpansionPanelSummary expandIcon={<ExpandMoreIcon />}>
                        <InsertCommentIcon />
                        <Typography className={classes.heading}>
                        Notas del pedido
                        </Typography>
                    </ExpansionPanelSummary>
                    <ExpansionPanelDetails className={classes.panelDetails}>
                    <Grid item xs={12} sm={12}>
                        <TextField
                            name="notes"
                            onChange={(event) => handleInputChangeOrder(event.target, 'text') }
                            label="Notas"
                            multiline
                            fullWidth
                            variant="outlined"
                            rowsMax={8}
                            />
                    </Grid>
                    </ExpansionPanelDetails>
                </ExpansionPanel>

                <ExpansionPanel disabled className={classes.panelTotal}>
                    <ExpansionPanelSummary className={classes.panelTotal}>
                        <AttachMoneyIcon />
                            <Typography className={classes.headingTotal}>
                                Total pedido: ${order.total}
                            </Typography>
                    </ExpansionPanelSummary>
                </ExpansionPanel>

                <Grid container className={classes.gridButtonsFooter} spacing={24}>
                        <Grid item xs={12}>
                            <Button 
                                className={classes.ButtonsOrder}
                                variant="contained" 
                                color="primary"
                                disabled={(order.total > 0) ? false : true}
                                onClick={() => saveOrder()}
                            >
                                Guardar
                            </Button>
                        </Grid>   
                       
                </Grid>   

                    
               
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
    table: {
        margin: '8px 0'
    },
    cell: {
        padding: '8px 4px'
    },
    cell_subtotal: {
        padding: '8px 4px !important',
        display: 'flex',
        flexDirection: 'column',
        textAlign: 'center',
        fontSize: theme.typography.pxToRem(14),
    },

    pageContent: {
        padding: theme.spacing.unit * 3,
    },
    panelDetails: {
        flexDirection: 'column',
        padding: '8px 4px'
    },
    panelDetailsRow: {
        flexDirection: 'column',
        [theme.breakpoints.up('md')]: {
            flexDirection: 'row',
          },
        padding: '8px 4px'
    },
    selectNative: {
        display: 'block'
    },
    gridButtonsFooter : {
        margin: '8px',
        width: 'auto',
        alignItems: 'center'
    },
    ButtonsOrder : {
 
        width: '100%',

    },
    panelTotal : {
        backgroundColor: 'rgb(9 202 0 / 12%)',
        opacity: 'unset !important'
    },
    root: {
        width: '100%',
      },
      heading: {
        fontSize: theme.typography.pxToRem(18),
        flexBasis: '90%',
        flexShrink: 0,
        fontWeight: 500,
        marginLeft: '8px',
      },
      headingTotal: {
        fontSize: theme.typography.pxToRem(18),
        fontWeight: 'bold'
      },
      secondaryHeading: {
        fontSize: theme.typography.pxToRem(15),
        color: theme.palette.text.secondary,
      },
});

export default withStyles(styles)(Create);
