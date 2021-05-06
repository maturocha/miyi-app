import React from 'react';

import { Link, Typography, withStyles } from '@material-ui/core';

const Footer = props => (
    <footer {...props} className={props.classes.root}>
        <Typography>
            By{' '}
            <Link
                href="https://www.linkedin.com/in/mat%C3%ADas-rocha/"
                target="_blank"
                rel="noreferrer"
            >
                @maturocha
            </Link>
        </Typography>
    </footer>
);

const styles = theme => ({
    root: {
        position: 'absolute',
        right: 0,
        bottom: 0,
        left: 0,
        padding: theme.spacing.unit * 4,
        textAlign: 'center',
    },
});

export default withStyles(styles)(Footer);
