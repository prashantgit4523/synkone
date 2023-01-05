import { createSlice, createAsyncThunk, createAction } from '@reduxjs/toolkit'

/* Fetches the campaigns list */
export const fetchStandardFilterData = createAsyncThunk('controls/fetchStandardFilterData', async (params) => {
    const response = await axiosFetch.get('kpi/standard-filter-data', {
        params: params
    })
    return response.data
})

export const updateSelectedStandards = createAction('controls/updateSelectedStandards')
