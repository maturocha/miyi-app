import React from 'react';

import { Master as MasterLayout } from './layouts';

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
        >
            Miyi Panel
        </MasterLayout>
    );
}

export default Home;
