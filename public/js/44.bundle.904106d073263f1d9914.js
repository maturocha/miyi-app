(window["webpackJsonp"] = window["webpackJsonp"] || []).push([[44],{

/***/ "./resources/js/views/auth/passwords/Request.js":
/*!******************************************************!*\
  !*** ./resources/js/views/auth/passwords/Request.js ***!
  \******************************************************/
/*! exports provided: default */
/***/ (function(module, __webpack_exports__, __webpack_require__) {

"use strict";
eval("__webpack_require__.r(__webpack_exports__);\n/* harmony import */ var _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/extends */ \"./node_modules/@babel/runtime/helpers/extends.js\");\n/* harmony import */ var _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__);\n/* harmony import */ var _babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @babel/runtime/helpers/objectWithoutProperties */ \"./node_modules/@babel/runtime/helpers/objectWithoutProperties.js\");\n/* harmony import */ var _babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1__);\n/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @babel/runtime/regenerator */ \"./node_modules/@babel/runtime/regenerator/index.js\");\n/* harmony import */ var _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2__);\n/* harmony import */ var _babel_runtime_helpers_asyncToGenerator__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @babel/runtime/helpers/asyncToGenerator */ \"./node_modules/@babel/runtime/helpers/asyncToGenerator.js\");\n/* harmony import */ var _babel_runtime_helpers_asyncToGenerator__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_asyncToGenerator__WEBPACK_IMPORTED_MODULE_3__);\n/* harmony import */ var _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @babel/runtime/helpers/slicedToArray */ \"./node_modules/@babel/runtime/helpers/slicedToArray.js\");\n/* harmony import */ var _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_4__);\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react */ \"./node_modules/react/index.js\");\n/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_5__);\n/* harmony import */ var react_router_dom__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react-router-dom */ \"./node_modules/react-router-dom/es/index.js\");\n/* harmony import */ var formik__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! formik */ \"./node_modules/formik/dist/formik.esm.js\");\n/* harmony import */ var yup__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! yup */ \"./node_modules/yup/lib/index.js\");\n/* harmony import */ var yup__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(yup__WEBPACK_IMPORTED_MODULE_8__);\n/* harmony import */ var _material_ui_core__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! @material-ui/core */ \"./node_modules/@material-ui/core/index.es.js\");\n/* harmony import */ var _helpers_Navigation__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../../../helpers/Navigation */ \"./resources/js/helpers/Navigation.js\");\n/* harmony import */ var _helpers_URL__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ../../../helpers/URL */ \"./resources/js/helpers/URL.js\");\n/* harmony import */ var _layouts__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ../../layouts */ \"./resources/js/views/layouts/index.js\");\n\n\n\n\n\n\n\n\n\n\n\n\n\n\nfunction PasswordRequest(props) {\n  var _useState = Object(react__WEBPACK_IMPORTED_MODULE_5__[\"useState\"])(false),\n      _useState2 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_4___default()(_useState, 2),\n      loading = _useState2[0],\n      setLoading = _useState2[1];\n\n  var _useState3 = Object(react__WEBPACK_IMPORTED_MODULE_5__[\"useState\"])(''),\n      _useState4 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_4___default()(_useState3, 2),\n      email = _useState4[0],\n      setEmail = _useState4[1];\n\n  var _useState5 = Object(react__WEBPACK_IMPORTED_MODULE_5__[\"useState\"])({}),\n      _useState6 = _babel_runtime_helpers_slicedToArray__WEBPACK_IMPORTED_MODULE_4___default()(_useState5, 2),\n      message = _useState6[0],\n      setMessage = _useState6[1];\n  /**\n   * Event listener that is triggered when the password request form is submitted.\n   *\n   * @param {object} event\n   * @param {object} form\n   *\n   * @return {undefined}\n   */\n\n\n  var handleRequestPasswordSubmit =\n  /*#__PURE__*/\n  function () {\n    var _ref2 = _babel_runtime_helpers_asyncToGenerator__WEBPACK_IMPORTED_MODULE_3___default()(\n    /*#__PURE__*/\n    _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2___default.a.mark(function _callee(values, _ref) {\n      var setSubmitting, setErrors, history, _email, routeSuffix, errors;\n\n      return _babel_runtime_regenerator__WEBPACK_IMPORTED_MODULE_2___default.a.wrap(function _callee$(_context) {\n        while (1) {\n          switch (_context.prev = _context.next) {\n            case 0:\n              setSubmitting = _ref.setSubmitting, setErrors = _ref.setErrors;\n              setSubmitting(false);\n              _context.prev = 2;\n              setLoading(true);\n              history = props.history;\n              _email = values.email;\n              routeSuffix = _helpers_Navigation__WEBPACK_IMPORTED_MODULE_10__[\"route\"]('auth.passwords.reset');\n              _context.next = 9;\n              return axios.post('api/v1/auth/password/request', {\n                email: _email,\n                routeSuffix: routeSuffix\n              });\n\n            case 9:\n              setLoading(false);\n              setMessage({\n                type: 'success',\n                title: 'Link Sent',\n                body: \"Check your email to reset your account. Thank you.\",\n                action: function action() {\n                  return history.push(\"/signin?username=\".concat(_email));\n                }\n              });\n              _context.next = 22;\n              break;\n\n            case 13:\n              _context.prev = 13;\n              _context.t0 = _context[\"catch\"](2);\n\n              if (_context.t0.response) {\n                _context.next = 19;\n                break;\n              }\n\n              setLoading(false);\n              setMessage({\n                type: 'error',\n                title: 'Something went wrong',\n                body: \"Oops? Something went wrong here. Please try again.\",\n                action: function action() {\n                  return window.location.reload();\n                }\n              });\n              return _context.abrupt(\"return\");\n\n            case 19:\n              errors = _context.t0.response.data.errors;\n              setErrors(errors);\n              setLoading(false);\n\n            case 22:\n            case \"end\":\n              return _context.stop();\n          }\n        }\n      }, _callee, null, [[2, 13]]);\n    }));\n\n    return function handleRequestPasswordSubmit(_x, _x2) {\n      return _ref2.apply(this, arguments);\n    };\n  }();\n\n  Object(react__WEBPACK_IMPORTED_MODULE_5__[\"useEffect\"])(function () {\n    if (email === '') {\n      return;\n    }\n\n    var location = props.location;\n    setEmail(_helpers_URL__WEBPACK_IMPORTED_MODULE_11__[\"queryParams\"](location.search).hasOwnProperty('username') ? _helpers_URL__WEBPACK_IMPORTED_MODULE_11__[\"queryParams\"](location.search).username : '');\n  });\n\n  var classes = props.classes,\n      other = _babel_runtime_helpers_objectWithoutProperties__WEBPACK_IMPORTED_MODULE_1___default()(props, [\"classes\"]);\n\n  var location = props.location;\n  return react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_layouts__WEBPACK_IMPORTED_MODULE_12__[\"Auth\"], _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({}, other, {\n    title: Lang.get('navigation.password_request_title'),\n    subTitle: \"\",\n    loading: loading,\n    message: message\n  }), react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(formik__WEBPACK_IMPORTED_MODULE_7__[\"Formik\"], {\n    initialValues: {\n      email: !email ? _helpers_URL__WEBPACK_IMPORTED_MODULE_11__[\"queryParams\"](location.search).username : email\n    },\n    onSubmit: handleRequestPasswordSubmit,\n    validationSchema: yup__WEBPACK_IMPORTED_MODULE_8__[\"object\"]().shape({\n      email: yup__WEBPACK_IMPORTED_MODULE_8__[\"string\"]().required(Lang.get('validation.required', {\n        attribute: 'email'\n      }))\n    })\n  }, function (_ref3) {\n    var values = _ref3.values,\n        handleChange = _ref3.handleChange,\n        errors = _ref3.errors,\n        submitCount = _ref3.submitCount,\n        isSubmitting = _ref3.isSubmitting;\n    return react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(formik__WEBPACK_IMPORTED_MODULE_7__[\"Form\"], {\n      autoComplete: \"off\"\n    }, react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"Grid\"], {\n      container: true,\n      direction: \"column\"\n    }, react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"Grid\"], {\n      item: true,\n      className: classes.formGroup\n    }, react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"TextField\"], {\n      id: \"email\",\n      type: \"email\",\n      label: \"Email\",\n      value: values.email,\n      onChange: handleChange,\n      variant: \"outlined\",\n      fullWidth: true,\n      error: submitCount > 0 && errors.hasOwnProperty('email'),\n      helperText: submitCount > 0 && errors.hasOwnProperty('email') && errors.email\n    })), react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"Grid\"], {\n      item: true,\n      className: classes.formGroup\n    }, react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"Link\"], {\n      component: function component(props) {\n        return react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(react_router_dom__WEBPACK_IMPORTED_MODULE_6__[\"Link\"], _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0___default()({}, props, {\n          to: {\n            search: _helpers_URL__WEBPACK_IMPORTED_MODULE_11__[\"queryString\"]({\n              username: email\n            }),\n            pathname: _helpers_Navigation__WEBPACK_IMPORTED_MODULE_10__[\"route\"]('auth.signin')\n          }\n        }));\n      }\n    }, Lang.get('navigation.signin')))), react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"Grid\"], {\n      container: true,\n      justify: \"space-between\"\n    }, react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"Grid\"], {\n      item: true\n    }), react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"Grid\"], {\n      item: true,\n      className: classes.formGroup\n    }, react__WEBPACK_IMPORTED_MODULE_5___default.a.createElement(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"Button\"], {\n      type: \"submit\",\n      variant: \"contained\",\n      color: \"primary\",\n      disabled: errors && Object.keys(errors).length > 0 && submitCount > 0 || isSubmitting\n    }, Lang.get('navigation.send_link')))));\n  }));\n}\n\nvar styles = function styles(theme) {\n  return {\n    formGroup: {\n      padding: theme.spacing.unit * 2,\n      paddingTop: 0\n    }\n  };\n};\n\nvar Styled = Object(_material_ui_core__WEBPACK_IMPORTED_MODULE_9__[\"withStyles\"])(styles)(PasswordRequest);\n/* harmony default export */ __webpack_exports__[\"default\"] = (Object(formik__WEBPACK_IMPORTED_MODULE_7__[\"withFormik\"])({})(Styled));\n\n//# sourceURL=webpack:///./resources/js/views/auth/passwords/Request.js?");

/***/ })

}]);