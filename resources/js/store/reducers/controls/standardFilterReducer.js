import { createAction, createReducer } from '@reduxjs/toolkit'
import {fetchStandardFilterData,updateSelectedStandards} from '../../actions/controls/standardFilter';


const initialState = {
    status: 'idel',
    standards: [],
    selectedStandards: []
}

const standardFilterReducer = createReducer(initialState, (builder) => {
  builder
    .addCase(fetchStandardFilterData.fulfilled, (state, action) => {
        if (action.payload.success) {
            state.standards = action.payload.data
        }
    })
    .addCase(updateSelectedStandards, (state, action) => {
        state.selectedStandards = action.payload
    })
})

export default standardFilterReducer
