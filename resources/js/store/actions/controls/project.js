import { createSlice, createAsyncThunk, createAction } from '@reduxjs/toolkit'

/* Fetches the campaigns list */
export const fetchProjectList = createAsyncThunk('controls/fetchProjectList', async (params) => {
    const response = await axiosFetch.get('risks/projects/list', {
        params: params
    })

    return response.data.success ? response.data.data : []
})
