import { createSlice } from '@reduxjs/toolkit';

const  datatableSlice = createSlice({
    name: 'dataTableCount',
    initialState : {
        table : [],
    },
        
    reducers:{
        customDataTableAdd(state,action){
            const newTable = action.payload;
            const existingTable = state.table?.find((item)=> item.tableTag === newTable.tableTag);
            if(existingTable){
                existingTable.page=newTable.page;
                existingTable.perPage=newTable.perPage;
            }
            else{
                state.table.push({
                    page: 1,
                    perPage: 10,
                    tableTag: newTable.tableTag,
                })
            }
        },

        customDataTableSetInitialState(state,action){
            const newTable = action.payload;
            const existingTable = state.table?.find((item)=>item.tableTag === newTable.tableTag);
            if(!existingTable){
                state.table.push({
                    page: 1,
                    perPage: 10,
                    tableTag: newTable.tableTag,
                })
            }
            if(existingTable){
                existingTable.page = 1;
                existingTable.perPage = 10;
                existingTable.tableTag = newTable.tableTag;
            }
        },
        customTableSetPage(state,action){
            const newTable = action.payload;
            const existingTable = state.table?.find((item)=>item.tableTag === newTable.tableTag);
            if(existingTable){
                existingTable.page= newTable.page;
            }
        },
        customTableSetPerPage(state,action){
            const newTable = action.payload;
            const existingTable = state.table?.find((item)=>item.tableTag === newTable.tableTag);
            if(existingTable){
                existingTable.perPage= newTable.perPage;
            }
        },
        createState(state,action){
            const newTable = action.payload;
            const existingTable = state.table?.find((item)=>item.tableTag === newTable.tableTag);
            if(!existingTable){
                state.table.push({
                    page: 1,
                    perPage: 10,
                    tableTag: newTable.tableTag,
                })
            }
        }
    }
});

export const tableActions = datatableSlice.actions;

export default datatableSlice;

