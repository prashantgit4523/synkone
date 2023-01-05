import React, {forwardRef, Fragment, useEffect, useImperativeHandle, useState} from 'react';
import { MultiSelect } from "react-multi-select-component";
import { useDispatch, useSelector } from 'react-redux';
import { fetchProjectFilterData, updateSelectedProjects } from '../../../../store/actions/controls/projectFilter';
import { useDidMountEffect } from '../../../../custom-hooks';
import './project-filter.css'

function ProjectFilter(props, ref) {
    const dispatch = useDispatch()
    const appDataScope = useSelector(state => state.appDataScope.selectedDataScope.value)
    const { selectedDepartment } = useSelector(state => state.commonReducer.departmentFilterReducer)
    const { projects } = useSelector(state => state.riskReducer.projectFilterReducer)
    const [selected, setSelected] = useState([]);
    const [projectOptions, setProjectOptions] = useState([])

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
        selectAll()
    }, [projectOptions])


    // The component instance will be extended
    // with whatever you return from the callback passed
    // as the second argument
    useImperativeHandle(ref, () => ({
        selectAll
    }));

    /* Handling project select*/
    const handleProjectSelect = (selectedProjects) => {
        /*updating local state*/
        setSelected(selectedProjects)
    }

    /* Selects the all options */
    const selectAll = () => {
        setSelected(projectOptions)
    }

    /* Un-select all*/
    const unSelectAll = () => {

        setSelected([])
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
            <MultiSelect
                options={projectOptions}
                value={selected}
                onChange={handleProjectSelect}
                className="w-100 project-filter-container"
                overrideStrings={{
                    allItemsAreSelected: "All projects are selected",
                    search: "Search project(s)",
                    "selectSomeItems": "Select project(s)",
                }}
                labelledBy="Select project(s)"
            />
        </Fragment>
    );
}

export default forwardRef(ProjectFilter);
