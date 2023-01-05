import { createAction, createReducer } from '@reduxjs/toolkit'
import { fetchCalendarMorePopoverData, resetCalendarMorePopoverData } from '../../../actions/compliance/dashboard'


const initialState = {
    status: 'idel',
    calendarEvents: [],
    pageCount: null,
    currentPage: 0,
    totalCount: null,
    loading: false,
    calendarDate: null
}

const calendarMorePopoverReducer = createReducer(initialState, (builder) => {
  builder
    .addCase(fetchCalendarMorePopoverData.fulfilled, (state, action) => {
        if (action.payload.success) {
            let {calendarTasks,currentPage, pageCount } = action.payload.data
            let newTasks = calendarTasks.map(task=> JSON.parse(task))

            if((state.calendarDate == null || action.meta.arg.date === state.calendarDate)){
                state.calendarEvents = [...state.calendarEvents, ...newTasks ]
            }else{
                state.calendarEvents = newTasks
            }

            // state.calendarEvents = [...state.calendarEvents, ...newTasks ] //Dublicated calendar events on calendar
            state.calendarEvents = [...state.calendarEvents]
            state.currentPage = currentPage
            state.pageCount = pageCount
            state.calendarDate = action.meta.arg.date
        }
        state.loading = false

    })
      .addCase(fetchCalendarMorePopoverData.pending, (state) => {
          state.loading = true
      })
    .addCase(resetCalendarMorePopoverData, (state) => {
        return initialState
    })
})

export default calendarMorePopoverReducer
