(window["webpackJsonp"] = window["webpackJsonp"] || []).push([[26],{

/***/ "./resources/js/views/__backoffice/users/Create.js":
/*!*********************************************************!*\
  !*** ./resources/js/views/__backoffice/users/Create.js ***!
  \*********************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/extends */ \"./node_modules/@babel/runtime/helpers/extends.js\");\n/* harmony import */ var _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @babel/runtime/helpers/objectWithoutProperties */ \"./node_modules/@babel/runtime/helpers/objectWithoutProperties.js\");\n/* harmony import */ var _babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__);\n/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @babel/runtime/regenerator */ \"./node_modules/@babel/runtime/regenerator/index.js\");\n/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2__);\n/* harmony import */ var _babel_runtime_helpers_toConsumableArray__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @babel/runtime/helpers/toConsumableArray */ \"./node_modules/@babel/runtime/helpers/toConsumableArray.js\");\n/* harmony import */ var _babel_runtime_helpers_toConsumableArray__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_toConsumableArray__WEBPACK_IMPORTED_MODULE_3__);\n/* harmony import */ var _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @babel/runtime/helpers/defineProperty */ \"./node_modules/@babel/runtime/helpers/defineProperty.js\");\n/* harmony import */ var _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_4__);\n/* harmony import */ var _babel_runtime_helpers_asyncToGenerator__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @babel/runtime/helpers/asyncToGenerator */ \"./node_modules/@babel/runtime/helpers/asyncToGenerator.js\");\n/* harmony import */ var _babel_runtime_helpers_asyncToGenerator__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_asyncToGenerator__WEBPACK_IMPORTED_MODULE_5__);\n/* harmony import */ var _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @babel/runtime/helpers/slicedToArray */ \"./node_modules/@babel/runtime/helpers/slicedToArray.js\");\n/* harmony import */ var _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_6__);\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react */ \"./node_modules/react/index.js\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_7__);\n/* harmony import */ var _material_ui_core__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @material-ui/core */ \"./node_modules/@material-ui/core/index.es.js\");\n/* harmony import */ var _helpers_Navigation__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../../../helpers/Navigation */ \"./resources/js/helpers/Navigation.js\");\n/* harmony import */ var _models__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../../../models */ \"./resources/js/models/index.js\");\n/* harmony import */ var _ui_Loaders__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ../../../ui/Loaders */ \"./resources/js/ui/Loaders/index.js\");\n/* harmony import */ var _layouts__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ../layouts */ \"./resources/js/views/__backoffice/layouts/index.js\");\n/* harmony import */ var _Forms__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ./Forms */ \"./resources/js/views/__backoffice/users/Forms/index.js\");\n\n\n\n\n\n\n\n\nfunction ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); if (enumerableOnly) symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; }); keys.push.apply(keys, symbols); } return keys; }\n\nfunction _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i] != null ? arguments[i] : {}; if (i % 2) { ownKeys(source, true).forEach(function (key) { _babel_runtime_helpers_defineProperty__WEBPACK_IMPORTED_MODULE_4___default()(target, key, source[key]); }); } else if (Object.getOwnPropertyDescriptors) { Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)); } else { ownKeys(source).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } } return target; }\n\n\n\n\n\n\n\n\n\nfunction Create(props) {\n  var _useState = Object(react__WEBPACK_IMPORTED_MODULE_7__[\"useState\"])(false),\n      _useState2 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_6___default()(_useState, 2),\n      loading = _useState2[0],\n      setLoading = _useState2[1];\n\n  var _useState3 = Object(react__WEBPACK_IMPORTED_MODULE_7__[\"useState\"])(0),\n      _useState4 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_6___default()(_useState3, 2),\n      activeStep = _useState4[0],\n      setActiveStep = _useState4[1];\n\n  var _useState5 = Object(react__WEBPACK_IMPORTED_MODULE_7__[\"useState\"])([]),\n      _useState6 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_6___default()(_useState5, 2),\n      formValues = _useState6[0],\n      setFormValues = _useState6[1];\n\n  var _useState7 = Object(react__WEBPACK_IMPORTED_MODULE_7__[\"useState\"])({}),\n      _useState8 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_6___default()(_useState7, 2),\n      user = _useState8[0],\n      setUser = _useState8[1];\n\n  var _useState9 = Object(react__WEBPACK_IMPORTED_MODULE_7__[\"useState\"])({}),\n      _useState10 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_6___default()(_useState9, 2),\n      message = _useState10[0],\n      setMessage = _useState10[1];\n  /**\n   * This should return back to the previous step.\n   *\n   * @return {undefined}\n   */\n\n\n  var handleBack = function handleBack() {\n    setActiveStep(activeStep - 1);\n  };\n  /**\n   * Handle form submit, this should send an API response\n   * to create a user.\n   *\n   * @param {object} values\n   *\n   * @param {object} form\n   *\n   * @return {undefined}\n   */\n\n\n  var handleSubmit =\n  /*#__PURE__*/\n  function () {\n    var _ref2 = _babel_runtime_helpers_asyncToGenerator__WEBPACK_IMPORTED_MODULE_5___default()(\n    /*#__PURE__*/\n    _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2___default.a.mark(function _callee(values, _ref) {\n      var setSubmitting, setErrors, previousValues, _user, newFormValues, errors;\n\n      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2___default.a.wrap(function _callee$(_context) {\n        while (1) {\n          switch (_context.prev = _context.next) {\n            case 0:\n              setSubmitting = _ref.setSubmitting, setErrors = _ref.setErrors;\n              setSubmitting(false); // Stop here as it is the last step...\n\n              if (!(activeStep === 2)) {\n                _context.next = 4;\n                break;\n              }\n\n              return _context.abrupt(\"return\");\n\n            case 4:\n              setLoading(true);\n              _context.prev = 5;\n              previousValues = {}; // Merge the form values here.\n\n              if (activeStep === 1) {\n                previousValues = formValues.reduce(function (prev, next) {\n                  return _objectSpread({}, prev, {}, next);\n                });\n              } // Instruct the API the current step.\n\n\n              values.step = activeStep;\n              _context.next = 11;\n              return _models__WEBPACK_IMPORTED_MODULE_10__[\"User\"].store(_objectSpread({}, previousValues, {}, values));\n\n            case 11:\n              _user = _context.sent;\n              // After persisting the previous values. Move to the next step...\n              newFormValues = _babel_runtime_helpers_toConsumableArray__WEBPACK_IMPORTED_MODULE_3___default()(formValues);\n              newFormValues[activeStep] = values;\n\n              if (activeStep === 1) {\n                setMessage({\n                  type: 'success',\n                  body: Lang.get('resources.created', {\n                    name: 'User'\n                  }),\n                  closed: function closed() {\n                    return setMessage({});\n                  }\n                });\n              }\n\n              setLoading(false);\n              setFormValues(newFormValues);\n              setUser(_user);\n              setActiveStep(activeStep + 1);\n              _context.next = 28;\n              break;\n\n            case 21:\n              _context.prev = 21;\n              _context.t0 = _context[\"catch\"](5);\n\n              if (_context.t0.response) {\n                _context.next = 25;\n                break;\n              }\n\n              throw new Error('Unknown error');\n\n            case 25:\n              errors = _context.t0.response.data.errors;\n              setErrors(errors);\n              setLoading(false);\n\n            case 28:\n            case \"end\":\n              return _context.stop();\n          }\n        }\n      }, _callee, null, [[5, 21]]);\n    }));\n\n    return function handleSubmit(_x, _x2) {\n      return _ref2.apply(this, arguments);\n    };\n  }();\n\n  var classes = props.classes,\n      other = _babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default()(props, [\"classes\"]);\n\n  var history = props.history;\n  var steps = ['Profile', 'Account', 'Avatar'];\n\n  var renderForm = function renderForm() {\n    var defaultProfileValues = {\n      firstname: '',\n      middlename: '',\n      lastname: '',\n      gender: '',\n      birthdate: null,\n      address: ''\n    };\n\n    switch (activeStep) {\n      case 0:\n        return react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_Forms__WEBPACK_IMPORTED_MODULE_13__[\"Profile\"], _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({}, other, {\n          values: formValues[0] ? formValues[0] : defaultProfileValues,\n          handleSubmit: handleSubmit\n        }));\n\n      case 1:\n        return react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_Forms__WEBPACK_IMPORTED_MODULE_13__[\"Account\"], _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({}, other, {\n          values: {\n            type: '',\n            email: '',\n            username: ''\n          },\n          handleSubmit: handleSubmit,\n          handleBack: handleBack\n        }));\n\n      case 2:\n        return react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_Forms__WEBPACK_IMPORTED_MODULE_13__[\"Avatar\"], _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({}, other, {\n          user: user,\n          handleSkip: function handleSkip() {\n            return history.push(_helpers_Navigation__WEBPACK_IMPORTED_MODULE_9__[\"route\"]('backoffice.resources.users.index'));\n          }\n        }));\n\n      default:\n        throw new Error('Unknown step!');\n    }\n  };\n\n  return react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_layouts__WEBPACK_IMPORTED_MODULE_12__[\"Master\"], _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({}, other, {\n    pageTitle: \"Create a user\",\n    tabs: [],\n    message: message\n  }), react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(\"div\", {\n    className: classes.pageContentWrapper\n  }, loading && react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_ui_Loaders__WEBPACK_IMPORTED_MODULE_11__[\"LinearIndeterminate\"], null), react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_8__[\"Paper\"], null, react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(\"div\", {\n    className: classes.pageContent\n  }, react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_8__[\"Typography\"], {\n    component: \"h1\",\n    variant: \"h4\",\n    align: \"center\",\n    gutterBottom: true\n  }, \"User Creation\"), react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_8__[\"Stepper\"], {\n    activeStep: activeStep,\n    className: classes.stepper\n  }, steps.map(function (name) {\n    return react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_8__[\"Step\"], {\n      key: name\n    }, react__WEBPACK_IMPORTED_MODULE_7___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_8__[\"StepLabel\"], null, name));\n  })), renderForm()))));\n}\n\nvar styles = function styles(theme) {\n  return {\n    pageContentWrapper: {\n      width: '100%',\n      marginTop: theme.spacing.unit * 3,\n      minHeight: '75vh',\n      overflowX: 'auto'\n    },\n    pageContent: {\n      padding: theme.spacing.unit * 3\n    }\n  };\n};\n\n/* harmony default export */ __webpack_exports__[\"default\"] = (Object(_material_ui_core__WEBPACK_IMPORTED_MODULE_8__[\"withStyles\"])(styles)(Create));\n\n//# sourceURL=webpack:///./resources/js/views/__backoffice/users/Create.js?");

/***/ }),

/***/ "./resources/js/views/__backoffice/users/Forms/index.js":
/*!**************************************************************!*\
  !*** ./resources/js/views/__backoffice/users/Forms/index.js ***!
  \**************************************************************/
/*! exports provided: Account, Avatar, Profile */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, \"Account\", function() { return Account; });\n/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, \"Avatar\", function() { return Avatar; });\n/* harmony export (binding) */ __webpack_require__.d(__webpack_exports__, \"Profile\", function() { return Profile; });\n/* harmony import */ var _loadable_component__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @loadable/component */ \"./node_modules/@loadable/component/dist/loadable.esm.js\");\n\nvar Account = Object(_loadable_component__WEBPACK_IMPORTED_MODULE_0__[\"default\"])(function () {\n  return __webpack_require__.e(/*! import() */ 42).then(__webpack_require__.bind(null, /*! ./Account */ \"./resources/js/views/__backoffice/users/Forms/Account.js\"));\n});\nvar Avatar = Object(_loadable_component__WEBPACK_IMPORTED_MODULE_0__[\"default\"])(function () {\n  return __webpack_require__.e(/*! import() */ 17).then(__webpack_require__.bind(null, /*! ./Avatar */ \"./resources/js/views/__backoffice/users/Forms/Avatar.js\"));\n});\nvar Profile = Object(_loadable_component__WEBPACK_IMPORTED_MODULE_0__[\"default\"])(function () {\n  return Promise.all(/*! import() */[__webpack_require__.e(\"vendor\"), __webpack_require__.e(43)]).then(__webpack_require__.bind(null, /*! ./Profile */ \"./resources/js/views/__backoffice/users/Forms/Profile.js\"));\n});\n\n//# sourceURL=webpack:///./resources/js/views/__backoffice/users/Forms/index.js?");

/***/ })

}]);