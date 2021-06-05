import React , { useState } from 'react';
import IconButton from '@material-ui/core/IconButton';
import Menu from '@material-ui/core/Menu';
import MenuItem from '@material-ui/core/MenuItem';
import {
    ArrowDropDown as ArrowDropDownIcon
} from '@material-ui/icons';

import * as NavigationUtils from '../helpers/Navigation';

const MenuList = props => {
    const { idOrder, history } = props
    const [anchorEl, setAnchorEl] = useState(null);

  const handleClick = (event) => {
    setAnchorEl(event.currentTarget);
  }

  const handleClose = () => {
    setAnchorEl(null);
  }

  return (
    <div>
         <IconButton                  
            onClick={handleClick}
        >
            <ArrowDropDownIcon />
        </IconButton>
      <Menu id="simple-menu" anchorEl={anchorEl} open={Boolean(anchorEl)} onClose={handleClose}>
      <MenuItem
                                 onClick={() =>
                                    history.push(
                                        NavigationUtils.route(
                                            'backoffice.general.orders.view',
                                            {
                                                id: idOrder,
                                            },
                                        ),
                                    )
                                    
                                }
                            >Ver</MenuItem>
                            <MenuItem
                                 onClick={() =>
                                    history.push(
                                        NavigationUtils.route(
                                            'backoffice.general.orders.edit',
                                            {
                                                id: idOrder,
                                            },
                                        ),
                                    )
                                }
                            >Editar</MenuItem>
                            <MenuItem
                             onClick={() =>
                                history.push(
                                    NavigationUtils.route(
                                        'backoffice.general.orders.delete',
                                        {
                                            id: idOrder,
                                        },
                                    ),
                                )
                            }
                            >Eliminar</MenuItem>
      </Menu>
    </div>
  );
}

export default MenuList;
