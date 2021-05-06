import React from 'react';
import PropTypes from 'prop-types';


import {
  withStyles,
} from '@material-ui/core';


const QuantitySpinner = ({
  increment, decrement, numberOfitems, classes
}) => {
  return (
    <div className={classes.container}>
      <button
        className={classes.button}
        onClick={decrement}>-</button>
      <p className={classes.p}>{numberOfitems}</p>
      <button
        className={classes.button}
        onClick={increment}
      >+</button>
    </div>
  )
}

const styles = theme => ({
  container: {
    display: 'flex',
    width: '100%',
    justifyContent: 'center',
    alignItems: 'center',
  },

  button: {
    height: '2.5rem',
    fontSize: '.875rem',
    lineHeight: '1.25rem',
    width: '2.5rem',
    textAlign: 'center',
    paddingBottom: '.125rem',
    borderWidth: '1px',
    backgroundColor: '#f3f4f6',
    cursor: 'pointer'
  },

  p: {
    width: '5rem',
    paddingTop: '.5rem',
    fontSize: '.75rem',
    lineHeight: '1.25rem',
    height: '2.5rem',
    textAlign: 'center',
    border: '1px solid #e5e7eb',
      
  }
});

export default withStyles(styles)(QuantitySpinner);