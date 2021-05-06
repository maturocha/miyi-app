import { Home } from '../views/__backoffice';
import * as Settings from '../views/__backoffice/settings';
import * as Users from '../views/__backoffice/users';
import * as Meetups from '../views/__backoffice/meetups';
import * as Orders from '../views/__backoffice/orders';

const resources = [
    {
        name: 'users.index',
        path: '/users',
        component: Users.List,
    },

    {
        name: 'users.create',
        path: '/users/create',
        component: Users.Create,
    },

    {
        name: 'users.edit',
        path: '/users/:id/edit',
        component: Users.Edit,
    },
].map(route => {
    route.name = `resources.${route.name}`;
    route.path = `/resources${route.path}`;

    return route;
});

const admin = [
    {
        name: 'meetups.index',
        path: '/meetups',
        component: Meetups.ListAdmin,
    },

    {
        name: 'meetups.create',
        path: '/meetups/create',
        component: Meetups.Create,
    },

    {
        name: 'meetups.edit',
        path: '/meetups/:id/edit',
        component: Meetups.Edit,
    },
].map(route => {
    route.name = `admin.${route.name}`;
    route.path = `/admin${route.path}`;

    return route;
});

const general = [
    {
        name: 'orders.index',
        path: '/pedidos',
        component: Orders.ListUser,
    },
    {
        name: 'orders.create',
        path: '/pedidos/crear',
        component: Orders.Create,
    },

    {
        name: 'orders.edit',
        path: '/pedidos/:id/editar',
        component: Orders.Edit,
    },

    {
        name: 'orders.view',
        path: '/pedidos/:id/ver',
        component: Orders.Show,
    },

].map(route => {
    route.name = `general.${route.name}`;
    route.path = `/general${route.path}`;

    return route;
});

export default [
    {
        name: 'home',
        path: '/',
        component: Home,
    },

    {
        name: 'settings.profile',
        path: '/settings/profile',
        component: Settings.Profile,
    },

    {
        name: 'settings.account',
        path: '/settings/account',
        component: Settings.Account,
    },

    ...resources,
    ...admin,
    ...general,
].map(route => {
    route.name = `backoffice.${route.name}`;
    route.auth = true;

    return route;
});
