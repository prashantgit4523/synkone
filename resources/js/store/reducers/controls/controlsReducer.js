import { createAction, createReducer } from '@reduxjs/toolkit'
import { fetchControlList } from '../../actions/controls/control'

const initialState = {
    loading: false,
    controls: [],
}

const controlsReducer = createReducer(initialState, (builder) => {
  builder
    .addCase(fetchControlList.pending, (state, action) => {
        state.loading = true
    })
    .addCase(fetchControlList.fulfilled, (state, action) => {
        state.controls = action.payload
        state.loading = false
    })
    .addCase(fetchControlList.rejected, (state, action) => {
        state.loading = false
    })
})

export default controlsReducer
