import { combineReducers } from 'redux'
import projectReducer from './projectReducer'
import projectFilterReducer from './projectFilterReducer'
import standardFilterReducer from './standardFilterReducer'
import controlsReducer from './controlsReducer'
import evidenceDataReducer from './evidenceDataReducer'

export default combineReducers({
    projectReducer,
    projectFilterReducer,
    standardFilterReducer,
    controlsReducer,
    evidenceDataReducer
})