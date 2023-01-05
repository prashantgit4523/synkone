import { combineReducers } from 'redux'

import dataScopeReducer from './dataScopeReducer'
import commonReducer from './common'
import globalDashboardReducer from './global-dashboard'
import policyManagement from './policy-management'
import pageLoaderReducer from './page-loader'
import complianceReducer from './compliance'
import riskReducer from './risk-management'
import reportGenerateLoaderReducer from './reportGenerateLoaderReducer'
import riskGenerateLoaderReducer from "./riskGenerateLoaderReducer";
import controlReducer from "./controls";
import datatableSlice from "../dataTable/customDataTable";
import pageDataReducer from './datatable/pageDataReducer';
import dataTableSlice from "../slices/dataTableSlice";
import awarenessReducer from './native-awareness';

const rootReducer = combineReducers({
  // Define a top-level state field named `todos`, handled by `todosReducer`
  appDataScope: dataScopeReducer,
  policyManagement: policyManagement,
  commonReducer: commonReducer,
  globalDashboardReducer: globalDashboardReducer,
  pageLoaderReducer: pageLoaderReducer,
  complianceReducer: complianceReducer,
  riskReducer: riskReducer,
  controlReducer: controlReducer,
  reportGenerateLoaderReducer: reportGenerateLoaderReducer,
  riskGenerateLoaderReducer: riskGenerateLoaderReducer,
  customDataTable : datatableSlice.reducer,
  pageDataReducer: pageDataReducer,
  datatable: dataTableSlice,
  awarenessReducer: awarenessReducer,
})

export default rootReducer
