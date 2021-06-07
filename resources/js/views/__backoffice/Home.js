import React from 'react';

import { Master as MasterLayout } from './layouts';
import AddIcon from '@material-ui/icons/Add';

function Home(props) {

    const tabs = [
        {
            name: 'Resumen',
            active: true,
        },
    ];

    return (
        <MasterLayout
            {...props}
            pageTitle='Panel'
            tabs={tabs}
            floatingButton={{
                route: 'backoffice.general.orders.create',
                icon: <AddIcon />,
                }
            }
        >
            Miyi Panel
        </MasterLayout>
    );
}

export default Home;
