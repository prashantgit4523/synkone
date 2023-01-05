import React, {forwardRef, Fragment, useEffect, useImperativeHandle, useState} from 'react';
import { MultiSelect } from "react-multi-select-component";
import { useDispatch, useSelector } from 'react-redux';
import { fetchProjectFilterData, updateSelectedProjects } from '../../../../../store/actions/risk-management/projectFilter';
import { useDidMountEffect } from '../../../../../custom-hooks';
import './project-filter.css';
import './project-filter.scss'
import { usePage } from "@inertiajs/inertia-react";

function ProjectFilter(props, ref) {
    const dispatch = useDispatch()
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value)
    const { selectedDepartment } = useSelector(state => state.commonReducer.departmentFilterReducer)
    const { projects, selectedProjects } = useSelector(state => state.riskReducer.projectFilterReducer)
    const [selected, setSelected] = useState([]);
    const [projectOptions, setProjectOptions] = useState([]);
    const [selectedProjectsState, setSelectedProjectsState] = useState([]);
    const { previous_url } = usePage().props;

    /* Fires when selectedDepartment updates */
    useDidMountEffect(() => {
        dispatch(fetchProjectFilterData({
            data_scope: appDataScope,
            selected_departments: selectedDepartment.join()
        }))
    }, [selectedDepartment])

    /* Setting project filter options */
    useDidMountEffect(() => {
        /* Un-selecting prev-selected before re-render */
        // unSelectAll()

        let data = projects.map((project) => {
            return {
                value: project.id,
                label: project.name
            }
        })

        setProjectOptions(data)
    }, [projects])

    /* when project options are updated on data scope change */
    useDidMountEffect(() => {
            selectAll();
            //Set selected projects to [] on data scope change
            setSelectedProjectsState([]);
    }, [projectOptions])

    useEffect(() => {
        if(selectedProjects.length){
            let data = [];

            selectedProjects.map((id) => {
                let filteredData = projects.find(project => project.id === id);
                if(filteredData != null){
                    data.push({
                        value: filteredData.id,
                        label: filteredData.name
                    });
                }
            });

            setSelectedProjectsState(data);
        }
    },[]);


    // The component instance will be extended
    // with whatever you return from the callback passed
    // as the second argument
    useImperativeHandle(ref, () => ({
            selectAll
    }));

    /* Handling project select*/
    const handleProjectSelect = (selectedProject) => {
        /*updating local state*/
            setSelected(selectedProject)
    }

    /* Selects the all options */
    const selectAll = () => {
        if(selectedProjectsState.length === 0 || projectOptions.length === selectedProjectsState.length){
            setSelected(projectOptions)
        }else{
            if(previous_url.includes('risks/dashboard')){
                setSelected(selectedProjectsState);
            }else{
                setSelected(projectOptions)
            }
        }
    }

    /* updateSelectedProjects in Store */
    useDidMountEffect(() => {
        if(selected.length===0){
            dispatch(updateSelectedProjects([0]))
        }
        else{
            dispatch(updateSelectedProjects(selected.map(selectedProject => selectedProject.value)))
        }
    },[selected])


    return (
        <Fragment>
            <div id="common-project-filter">
            <MultiSelect
                options={projectOptions}
                value={selected}
                onChange={handleProjectSelect}
                className={`project-filter-container ${props.className}`}
                overrideStrings={{
                    allItemsAreSelected: "All projects are selected.",
                    search: "Search project(s)",
                    "selectSomeItems": "Select project(s)",
                }}
                labelledBy="Select project(s)"
            />
            </div>
        </Fragment>
    );
}

export default forwardRef(ProjectFilter);
