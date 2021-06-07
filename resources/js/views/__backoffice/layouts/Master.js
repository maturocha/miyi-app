import React, { useState, useEffect, useContext } from 'react';
import PropTypes from 'prop-types';

import classNames from 'classnames';
import {
    AppBar,
    Fab,
    CircularProgress,
    CssBaseline,
    Grid,
    Hidden,
    withStyles,
} from '@material-ui/core';

import * as NavigationUtils from '../../../helpers/Navigation';

import { Breadcrumbs, Snackbar, Modal } from '../../../ui';
import { LinearDeterminate } from '../../../ui/Loaders';
import { Footer, Header, Sidebar } from '../partials';
import { AppContext } from '../../../AppContext';


function Master(props) {
    const [minimized, setMinimized] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);
    const [notificationOpen, setNotificationOpen] = useState(false);
    const [accountMenuOpen, setAccountMenuOpen] = useState(false);
    const [notificationsList, setNotifications] = useState({});

    //  /**
    //  * Fetch data on initialize.
    //  */
    // useEffect(() => {
        
    //     fetchNotification();

    // }, []);

    // const fetchNotification = async (params = {}) => {
        


    //         //Get Meetups
    //         const notifications = await Notification.paginated();
    //         setNotifications(notifications);

            
      
    // };

    /**
     * Called when a nav link menu is clicked.
     *
     * @param {function} set The callback function to be called
     * @param {string} indicator The flag that will be toggled
     *
     * @return {undefined}
     */
    const handleNavLinkMenuToggled = (set, indicator) => {
        setAccountMenuOpen(false);
        setMobileOpen(false);
        setNotificationOpen(false);

        set(!indicator);
    };

    /**
     * Toggles Account Menu
     *
     * @return {undefined}
     */
    const handleAccountMenuToggled = () => {
        handleNavLinkMenuToggled(setAccountMenuOpen, accountMenuOpen);
    };

    /**
     * Toggles Account Menu
     *
     * @return {undefined}
     */
    const handleNotificationMenuToggled = () => {
        handleNavLinkMenuToggled(setNotificationOpen, notificationOpen);
    };

    

    /**
     * Called when mobile drawer button is clicked.
     *
     * @return {undefined}
     */
    const handleDrawerToggled = () => {
        setMobileOpen(!mobileOpen);
    };

    useEffect(() => {
        //
    });

    const { nightMode } = useContext(AppContext);

    const { classes, showBreadcrumbs, floatingButton, ...other } = props;

    const {
        children,
        history,
        location,
        pageTitle,
        loading,
        message,
        alert,
    } = props;

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

    const renderBreadcrumbs = (
        <AppBar
            component="div"
            color="inherit"
            position="static"
            elevation={0}
            className={classes.breadcrumbBar}
            style={{
                backgroundColor: nightMode ? '#303030' : '#FAFAFA',
            }}
        >
            <div className={classes.breadcrumbWrapper}>
                <Breadcrumbs
                    segments={location.pathname
                        .split('/')
                        .splice(1)
                        .filter(segment => segment.length > 0)}
                    blacklistedSegments={['resources', 'analytics']}
                />
            </div>
        </AppBar>
    );

    return (
        <>
            {loading && <LinearDeterminate className={classes.loader} />}

            <div className={classes.root}>
                <CssBaseline />

                <nav
                    className={classNames(classes.drawer, {
                        [classes.minimized]: minimized,
                    })}
                >
                    <Hidden smUp implementation="js">
                        <Sidebar
                            {...other}
                            loading={loading}
                            navigate={path => history.push(path)}
                            variant="temporary"
                            open={mobileOpen}
                            onClose={handleDrawerToggled}
                            PaperProps={{ style: { width: drawerWidth } }}
                        />
                    </Hidden>

                    <Hidden xsDown implementation="css">
                        <Sidebar
                            {...other}
                            loading={loading}
                            navigate={path => history.push(path)}
                            minimized={minimized}
                            setMinimized={setMinimized}
                            PaperProps={{
                                style: { width: minimized ? 70 : drawerWidth },
                            }}
                        />
                    </Hidden>
                </nav>

                <div className={classes.contentWrapper}>
                    <Header
                        {...other}
                        mobileOpen={mobileOpen}
                        accountMenuOpen={accountMenuOpen}
                        notificationOpen={notificationOpen}
                        notificationsList={notificationsList}
                        loading={loading}
                        onDrawerToggle={handleDrawerToggled}
                        onAccountMenuToggle={handleAccountMenuToggled}
                        onNotificationMenuToggle={handleNotificationMenuToggled}
                    />

                    {showBreadcrumbs && renderBreadcrumbs}

                    <main className={classes.content}>
                        {loading ? (
                            renderLoading
                        ) : (
                            <Grid container>{children}</Grid>
                        )}
                    </main>

                    {floatingButton &&
                        <Fab 
                            className={classNames(classes.fab, classes.fabGreen)} 
                            color='inherit'
                            onClick={() => history.push(
                                    NavigationUtils.route(
                                        floatingButton.route
                                    ),
                                )
                            }
                        >
                            {floatingButton.icon}
                        </Fab>
                    }

                    <Footer />
                </div>
            </div>

            {message && message.hasOwnProperty('type') > 0 && (
                <Snackbar {...message} />
            )}

            {alert && alert.hasOwnProperty('type') > 0 && <Modal {...alert} />}
        </>
    );
}

Master.propTypes = {
    classes: PropTypes.object.isRequired,
    pageTitle: PropTypes.string.isRequired,
    loading: PropTypes.bool,

    primaryAction: PropTypes.object,
    tabs: PropTypes.array,
    showBreadcrumbs: PropTypes.bool,
    message: PropTypes.object,
    alert: PropTypes.object,
};

Master.defaultProps = {
    loading: false,

    tabs: [],
    showBreadcrumbs: true,
    message: {},
    alert: {},
};

const drawerWidth = 256;

const styles = theme => ({
    loader: {
        zIndex: 9999,
    },

    root: {
        display: 'flex',
        position: 'relative',
        minHeight: '100vh',
        maxWidth: '100%',
    },

    drawer: {
        [theme.breakpoints.up('sm')]: {
            width: drawerWidth,
            flexShrink: 0,
        },

        '&$minimized': {
            [theme.breakpoints.up('sm')]: {
                width: 70,
            },
        },
    },

    minimized: {},

    breadcrumbBar: {
        zIndex: 0,
    },

    breadcrumbWrapper: {
        padding: theme.spacing.unit * 3,
    },

    contentWrapper: {
        flex: 1,
        display: 'flex',
        flexDirection: 'column',
        overflowX: 'auto',
    },

    fab: {
        position: 'fixed',
        bottom: theme.spacing.unit * 2,
        right: theme.spacing.unit * 2,
    },

    fabGreen: {
        color: theme.palette.common.white,
        backgroundColor: theme.palette.green.main,
        '&:hover': {
          backgroundColor: theme.palette.green.main,
        },
      },

    content: {
        flex: 1,
        padding: `0 ${theme.spacing.unit}px`,
        marginBottom: 75,
        [theme.breakpoints.up('sm')]: {
            padding: `${theme.spacing.unit}px ${theme.spacing.unit * 3}px`,
        },
    },

    loadingContainer: {
        minHeight: '100%',
    },
});

export default withStyles(styles)(Master);
