import { createSlice, createAsyncThunk, createAction } from '@reduxjs/toolkit'

/* Fetches the campaigns list */
export const fetchProjectFilterData = createAsyncThunk('controls/fetchProjectFilterData', async (params) => {
    const response = await axiosFetch.get('risks/projects/project-filter-data', {
        params: params
    })
    return response.data
})

export const updateSelectedProjects = createAction('controls/updateSelectedProjects')
