import { createSlice, createAsyncThunk, createAction } from '@reduxjs/toolkit'

/* Fetches the kpi control list */
export const fetchControlList = createAsyncThunk('kpi/fetchControlList', async (params) => {
    const response = await axiosFetch.get('/kpi-dashboard-data', {
        params: params
    })
    return response.data ;
})
