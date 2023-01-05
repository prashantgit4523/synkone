import { combineReducers } from 'redux'
import projectReducer from './projectReducer'
import projectFilterReducer from './projectFilterReducer'
import pageDataReducer from './pageDataReducer'

export default combineReducers({
    projectReducer,
    projectFilterReducer,
    pageDataReducer
})