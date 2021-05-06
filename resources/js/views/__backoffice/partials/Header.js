import React, { useCallback, useContext } from 'react';
import PropTypes from 'prop-types';

import {
    AppBar,
    Avatar,
    Badge,
    Button,
    ClickAwayListener,
    colors,
    Divider,
    Grid,
    Grow,
    Hidden,
    IconButton,
    ListItemAvatar,
    ListItemText,
    ListItemIcon,
    MenuList,
    MenuItem,
    Paper,
    Popper,
    Tab,
    Tabs,
    Toolbar,
    Tooltip,
    Typography,
    withStyles,
} from '@material-ui/core';

import {
    ExitToApp as ExitToAppIcon,
    Help as HelpIcon,
    Language as LanguageIcon,
    Lock as LockIcon,
    Menu as MenuIcon,
    Notifications as NotificationsIcon,
    Settings as SettingsIcon,
    Update as UpdateIcon,
    LinkedIn as LinkedInIcon,
    AccountCircle as AccountCircleIcon,
} from '@material-ui/icons';

import * as NavigationUtils from '../../../helpers/Navigation';
import * as RandomUtils from '../../../helpers/Random';
import {
    LightbulbOff as LightbulbOffIcon,
    LightbulbOn as LightbulbOnIcon,
} from '../../../icons/1x1';

import { Skeleton } from '../../../ui';
import { AppContext } from '../../../AppContext';


const AccountMenu = props => {
    const { user, handleLock, handleSignOut } = useContext(AppContext);

    const {
        history,
        classes,

        accountMenuOpen,
        onAccountMenuToggle,
    } = props;

    const navigate = path => history.push(path);

    return (
        <Popper
            open={accountMenuOpen}
            className={classes.navLinkMenu}
            transition
            disablePortal
        >
            {({ TransitionProps, placement }) => (
                <Grow
                    {...TransitionProps}
                    style={{
                        transformOrigin:
                            placement === 'bottom'
                                ? 'center top'
                                : 'center bottom',
                    }}
                >
                    <Paper style={{ marginTop: -3 }}>
                        <ClickAwayListener onClickAway={onAccountMenuToggle}>
                            <MenuList>
                                <MenuItem style={{ height: 50 }}>
                                    <ListItemAvatar
                                        className={classes.navLinkMenuItemIcon}
                                    >
                                        <AccountCircleIcon />
                                    </ListItemAvatar>

                                    <ListItemText>
                                        <Typography>{user.name}</Typography>

                                        <Typography color="textSecondary">
                                            {user.email}
                                        </Typography>
                                    </ListItemText>
                                </MenuItem>

                               

                                <MenuItem
                                    onClick={() => handleLock(user.username)}
                                >
                                    <ListItemIcon
                                        className={classes.navLinkMenuItemIcon}
                                    >
                                        <LockIcon />
                                    </ListItemIcon>

                                    <Typography>
                                        Lock
                                    </Typography>
                                </MenuItem>

                                <MenuItem onClick={handleSignOut}>
                                    <ListItemIcon
                                        className={classes.navLinkMenuItemIcon}
                                    >
                                        <ExitToAppIcon />
                                    </ListItemIcon>

                                    <Typography>
                                        Salir
                                    </Typography>
                                </MenuItem>
                            </MenuList>
                        </ClickAwayListener>
                    </Paper>
                </Grow>
            )}
        </Popper>
    );
};


const NotificationMenu = props => {
    const { user, handleLock, handleSignOut } = useContext(AppContext);

    const {
        history,
        classes,
        notificationsList,
        notificationOpen,
        onNotificationMenuToggle,
    } = props;

    const navigate = path => history.push(path);

    return (
        <Popper
            open={notificationOpen}
            className={classes.navLinkMenu}
            transition
            disablePortal
        >
            {({ TransitionProps, placement }) => (
                <Grow
                    {...TransitionProps}
                    style={{
                        transformOrigin:
                            placement === 'bottom'
                                ? 'center top'
                                : 'center bottom',
                    }}
                >
                    <Paper style={{ marginTop: -3 }}>
                        <ClickAwayListener onClickAway={onNotificationMenuToggle}>
                            <MenuList>
                                {notificationsList.map((item,i) => <MenuItem key={i}>
                                    <Typography>
                                        {item.message}
                                    </Typography>
                                </MenuItem>)
                                }
                            </MenuList>
                        </ClickAwayListener>
                    </Paper>
                </Grow>
            )}
        </Popper>
    );
};

function Header(props) {
    const {
        classes,
        pageTitle,
        loading,

        variant,
        primaryAction,
        tabs,
        notificationsList,
        accountMenuOpen,
        notificationOpen,
        onDrawerToggle,
        onNotificationMenuToggle,
        onAccountMenuToggle,
    } = props;

    const {
        user,
        nightMode,
        handleNightModeToggled,
    } = useContext(AppContext);

    const skeletonProps = {
        color: colors.grey[400],
        highlightColor: colors.grey[200],
    };

    const renderDrawerButtonNavigating = (
        <Grid item>
            <IconButton
                color="inherit"
                aria-label="Open drawer"
                className={classes.menuButton}
            >
                <Skeleton {...skeletonProps} height={25} width={25} />
            </IconButton>
        </Grid>
    );

    const renderNavigating = (
        <>
            <AppBar
                color="primary"
                position="sticky"
                elevation={0}
                className={
                    variant === 'slim'
                        ? classes.primaryBarSlim
                        : classes.primaryBar
                }
            >
                <Toolbar>
                    <Grid container spacing={8} alignItems="center">
                        {variant === 'slim' ? (
                            renderDrawerButtonNavigating
                        ) : (
                            <Hidden smUp>{renderDrawerButtonNavigating}</Hidden>
                        )}

                        <Hidden smDown>
                            {variant === 'slim' && (
                                <Grid item>
                                    <Skeleton
                                        {...skeletonProps}
                                        height={30}
                                        width={75 + pageTitle.length * 2}
                                    />
                                </Grid>
                            )}
                        </Hidden>

                        <Grid item xs />

                        <Grid item>
                            <IconButton color="inherit">
                                <Skeleton
                                    {...skeletonProps}
                                    circle
                                    height={25}
                                    width={25}
                                />
                            </IconButton>
                        </Grid>

                        <Grid item>
                            <IconButton color="inherit">
                                <Skeleton
                                    {...skeletonProps}
                                    circle
                                    height={25}
                                    width={25}
                                />
                            </IconButton>
                        </Grid>

                        <Grid item>
                            <IconButton color="inherit">
                                <Skeleton
                                    {...skeletonProps}
                                    circle
                                    height={25}
                                    width={25}
                                />
                            </IconButton>
                        </Grid>

                        <Grid item>
                            <IconButton color="inherit">
                                <Skeleton
                                    {...skeletonProps}
                                    circle
                                    height={25}
                                    width={25}
                                />
                            </IconButton>
                        </Grid>

                        <Grid item>
                            <IconButton color="inherit">
                                <Skeleton
                                    {...skeletonProps}
                                    circle
                                    height={25}
                                    width={25}
                                />
                            </IconButton>
                        </Grid>

                        <Grid item>
                            <IconButton color="inherit">
                                <Skeleton
                                    {...skeletonProps}
                                    circle
                                    height={30}
                                    width={30}
                                />
                            </IconButton>
                        </Grid>
                    </Grid>
                </Toolbar>
            </AppBar>

            {variant === 'full' && (
                <>
                    <AppBar
                        component="div"
                        className={classes.secondaryBar}
                        color="primary"
                        position="static"
                        elevation={0}
                    >
                        <Toolbar>
                            <Grid container alignItems="center" spacing={8}>
                                <Grid item xs>
                                    <Skeleton
                                        height={30}
                                        width={75 + pageTitle.length * 2}
                                        {...skeletonProps}
                                        className={classes.button}
                                    />
                                </Grid>

                                {Object.keys(primaryAction).length > 0 && (
                                    <Grid item>
                                        <Skeleton
                                            {...skeletonProps}
                                            height={25}
                                            width={
                                                50 +
                                                primaryAction.text.length * 2
                                            }
                                            className={classes.button}
                                        />
                                    </Grid>
                                )}

                                <Grid item>
                                    <IconButton color="inherit">
                                        <Skeleton
                                            {...skeletonProps}
                                            circle
                                            height={25}
                                            width={25}
                                        />
                                    </IconButton>
                                </Grid>
                            </Grid>
                        </Toolbar>
                    </AppBar>

                    {tabs.length > 0 && (
                        <AppBar
                            component="div"
                            className={classes.secondaryBar}
                            color="primary"
                            position="static"
                            elevation={0}
                        >
                            <Tabs value={0} textColor="inherit">
                                {tabs.map((tab, key) => (
                                    <Tab
                                        key={key}
                                        textColor="inherit"
                                        label={
                                            <Skeleton
                                                {...skeletonProps}
                                                height={20}
                                                width={25 + tab.name.length * 2}
                                            />
                                        }
                                    />
                                ))}
                            </Tabs>
                        </AppBar>
                    )}
                </>
            )}
        </>
    );

    const renderDrawerButton = (
        <Grid item>
            <Tooltip title='Open Drawer'>
                <IconButton
                    color="inherit"
                    aria-label="Open drawer"
                    onClick={onDrawerToggle}
                    className={classes.menuButton}
                >
                    <MenuIcon />
                </IconButton>
            </Tooltip>
        </Grid>
    );

    const renderNavigated = (
        <>
            <AppBar
                color="primary"
                position="sticky"
                elevation={0}
                className={
                    variant === 'slim'
                        ? classes.primaryBarSlim
                        : classes.primaryBar
                }
            >
                <Toolbar>
                    <Grid container spacing={8} alignItems="center">
                        {variant === 'slim' ? (
                            renderDrawerButton
                        ) : (
                            <Hidden smUp>{renderDrawerButton}</Hidden>
                        )}

                        <Hidden smDown>
                            {variant === 'slim' && (
                                <Grid item>
                                    <Typography color="inherit" variant="h5">
                                        {pageTitle}
                                    </Typography>
                                </Grid>
                            )}
                        </Hidden>

                        <Grid item xs />


                        <Grid item>
                            <Tooltip
                                title='Notificaciones'
                            >
                                <div className={classes.navLinkMenuWrapper}>
                                    <IconButton 
                                    aria-haspopup="true"
                                    onClick={onNotificationMenuToggle}
                                    color="inherit">
                                        
                                        <Badge
                                            badgeContent={(notificationsList.length > 0) ? 
                                                            notificationsList.length : ''
                                            }
                                            color="secondary"
                                        >
                                            <NotificationsIcon />
                                        </Badge>
                                    </IconButton>
                                    <NotificationMenu {...props} />
                                </div>
                            </Tooltip>
                        </Grid>

                        <Grid item>
                            <Tooltip
                                title={
                                    nightMode
                                        ? 'Modo Nocturno: Off'
                                        : 'Modo Nocturno: On'
                                }
                            >
                                <IconButton
                                    color="inherit"
                                    onClick={handleNightModeToggled}
                                >
                                    {nightMode ? (
                                        <LightbulbOnIcon />
                                    ) : (
                                        <LightbulbOffIcon />
                                    )}
                                </IconButton>
                            </Tooltip>
                        </Grid>

                        <Grid item>
                            <Tooltip title='Cuenta'>
                                <div className={classes.navLinkMenuWrapper}>
                                    <IconButton
                                        aria-owns={
                                            accountMenuOpen && 'material-appbar'
                                        }
                                        aria-haspopup="true"
                                        onClick={onAccountMenuToggle}
                                        color="inherit"
                                    >
                                      <AccountCircleIcon user={user} />
                                    </IconButton>

                                    <AccountMenu {...props} />
                                </div>
                            </Tooltip>
                        </Grid>
                    </Grid>
                </Toolbar>
            </AppBar>

            {variant === 'full' && (
                <>
                    <AppBar
                        component="div"
                        className={classes.secondaryBar}
                        color="primary"
                        position="static"
                        elevation={0}
                    >
                        <Toolbar>
                            <Grid container alignItems="center" spacing={8}>
                                <Grid item xs>
                                    <Typography color="inherit" variant="h5">
                                        {pageTitle}
                                    </Typography>
                                </Grid>

                                {Object.keys(primaryAction).length > 0 && (
                                    <Grid item>
                                        <Button
                                            className={classes.button}
                                            variant="outlined"
                                            color="inherit"
                                            size="small"
                                            onClick={primaryAction.clicked}
                                        >
                                            {primaryAction.text}
                                        </Button>
                                    </Grid>
                                )}

                            </Grid>
                        </Toolbar>
                    </AppBar>

                    {tabs.length > 0 && (
                        <AppBar
                            component="div"
                            className={classes.secondaryBar}
                            color="primary"
                            position="static"
                            elevation={0}
                        >
                            <Tabs value={0} textColor="inherit">
                                {tabs.map((tab, key) => (
                                    <Tab
                                        key={key}
                                        textColor="inherit"
                                        label={tab.name}
                                    />
                                ))}
                            </Tabs>
                        </AppBar>
                    )}
                </>
            )}
        </>
    );

    return <>{loading ? renderNavigating : renderNavigated}</>;
}

Header.propTypes = {
    classes: PropTypes.object.isRequired,
    pageTitle: PropTypes.string.isRequired,
    loading: PropTypes.bool,

    variant: PropTypes.oneOf(['full', 'slim']),
    primaryAction: PropTypes.object,
    tabs: PropTypes.array,
    notificationOpen: PropTypes.bool,
    accountMenuOpen: PropTypes.bool,
    onDrawerToggle: PropTypes.func,
    onNotificationMenuToggle: PropTypes.func,
    onAccountMenuToggle: PropTypes.func,
};

Header.defaultProps = {
    loading: false,

    variant: 'full',
    primaryAction: {},
    tabs: [],
    notificationOpen: false,
    accountMenuOpen: false,
};

const lightColor = 'rgba(255, 255, 255, 0.7)';

const styles = theme => ({
    primaryBar: {
        paddingTop: 8,
    },

    primaryBarSlim: {
        paddingTop: 8,
        paddingBottom: 8,
    },

    navLinkMenuWrapper: {
        position: 'relative',
        display: 'inline-block',
    },

    navLinkMenu: {
        position: 'absolute',
        padding: '8px 20px',
        right: 0,
        zIndex: 9999,
    },

    navLinkMenuItemIcon: {
        marginRight: 16,
    },

    secondaryBar: {
        zIndex: 0,
    },

    menuButton: {
        marginLeft: -theme.spacing.unit,
    },

    iconButtonAvatar: {
        padding: 4,
    },

    button: {
        borderColor: lightColor,
    },
});

export default withStyles(styles)(Header);
