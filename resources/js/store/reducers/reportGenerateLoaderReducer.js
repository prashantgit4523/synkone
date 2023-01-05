import { createAction, createReducer } from '@reduxjs/toolkit'

const show = createAction('reportGenerateLoader/show')
const hide = createAction('reportGenerateLoader/hide')

const initialState = {
    show: false,
    title: ''
}

const reportGenerateLoaderReducer = createReducer(initialState, (builder) => {
  builder
    .addCase(show, (state, action) => {
      state.show = true,
      state.title = action.payload ? action.payload : 'Generating Your Report'
    })
    .addCase(hide, (state, action) => {
        state.show = false
        state.title = ''
    })
})

export default reportGenerateLoaderReducer
