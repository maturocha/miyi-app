(window.webpackJsonp=window.webpackJsonp||[]).push([[30],{948:function(e,n,a){"use strict";a.r(n);var t=a(31),r=a.n(t),l=a(0),c=a.n(l),i=a(50),o=a.n(i),u=a(216),d=a.n(u),s=a(152),f=a.n(s),m=a(912),E=a(106);n.default=function(e){var n=e.idOrder,a=e.history,t=e.handleDeleteClick,i=Object(l.useState)(null),u=r()(i,2),s=u[0],k=u[1];return c.a.createElement("div",null,c.a.createElement(o.a,{onClick:function(e){k(e.currentTarget)}},c.a.createElement(m.a,null)),c.a.createElement(d.a,{id:"simple-menu",anchorEl:s,open:Boolean(s),onClose:function(){k(null)}},c.a.createElement(f.a,{onClick:function(){return a.push(E.a("backoffice.general.orders.view",{id:n}))}},"Ver"),c.a.createElement(f.a,{onClick:function(){return a.push(E.a("backoffice.general.orders.edit",{id:n}))}},"Editar"),c.a.createElement(f.a,{onClick:function(){return t(n)}},"Eliminar")))}}}]);